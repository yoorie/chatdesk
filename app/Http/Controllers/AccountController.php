<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Services\AccountProvisioner;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function workspaces(Request $request, AccountProvisioner $provisioner): Response
    {
        if ($request->user()?->isSuperAdmin() && ! $request->session()->has('impersonator_id')) {
            abort(403, 'Platform admins do not manage user workspaces here.');
        }

        $currentAccount = $request->attributes->get('account') ?? current_account();
        $accounts = $request->user()
            ->accounts()
            ->with(['owner', 'users:id', 'subscription.plan'])
            ->get()
            ->merge(
                $request->user()
                    ->ownedAccounts()
                    ->with(['owner', 'users:id', 'subscription.plan'])
                    ->get()
            )
            ->unique('id')
            ->values();

        $canCreateWorkspace = ! $request->user()?->isSuperAdmin()
            && app(\App\Services\PlatformSettingsService::class)->isFeatureEnabled('account_creation');

        return Inertia::render('App/Workspaces/Index', [
            'workspaces' => $accounts->map(function (Account $account) use ($request, $currentAccount, $accounts) {
                $role = $this->roleFor($request, $account);
                $canDelete = $role === 'owner' && $accounts->count() > 1;

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'slug' => $account->slug,
                    'workspace_type' => $account->workspace_type,
                    'workspace_type_label' => $account->workspace_type_label,
                    'industry' => $account->industry,
                    'timezone' => $account->timezone,
                    'status' => $account->status,
                    'role' => $role,
                    'is_current' => $currentAccount && account_ids_match($currentAccount->id, $account->id),
                    'users_count' => $this->teamMemberCount($account),
                    'plan' => $account->subscription?->plan ? [
                        'name' => $account->subscription->plan->name,
                        'key' => $account->subscription->plan->key,
                    ] : null,
                    'subscription' => $account->subscription ? [
                        'status' => $account->subscription->status,
                        'current_period_end' => $account->subscription->current_period_end?->toIso8601String(),
                        'cancel_at_period_end' => (bool) $account->subscription->cancel_at_period_end,
                    ] : null,
                    'can_delete' => $canDelete,
                    'delete_blocked_reason' => $role !== 'owner'
                        ? 'Only the workspace owner can delete this workspace.'
                        : ($accounts->count() <= 1 ? 'Create or join another workspace before deleting this one.' : null),
                ];
            })->values(),
            'canCreateWorkspace' => $canCreateWorkspace,
            'showCreatePanel' => $request->query('panel') === 'create',
            'plans' => $canCreateWorkspace
                ? $provisioner->publicPlans()->map(fn ($plan) => $provisioner->serializePlan($plan))->values()
                : [],
            'defaultPlanKey' => $canCreateWorkspace ? $provisioner->defaultPlanKey() : 'starter',
            'workspaceTypes' => Account::workspaceTypes(),
        ]);
    }

    public function storeWorkspace(Request $request, AccountProvisioner $provisioner)
    {
        if ($request->user()?->isSuperAdmin()) {
            abort(403, 'Platform admins cannot create user workspaces.');
        }

        $settingsService = app(\App\Services\PlatformSettingsService::class);

        if (! $settingsService->isFeatureEnabled('account_creation')) {
            abort(403, 'Workspace creation is currently disabled.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['required', 'string', 'in:business,agency,client,branch,project'],
            'industry' => ['nullable', 'string', 'max:120'],
            'plan_key' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $account = $provisioner->create($request->user(), $validated['name'], $validated['plan_key'] ?? null, [
                'workspace_type' => $validated['workspace_type'],
                'industry' => $validated['industry'] ?? null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->withErrors(['workspace' => 'Workspace could not be created: '.$e->getMessage()])
                ->with('error', 'Workspace could not be created. '.$e->getMessage());
        }

        session(['current_account_id' => $account->id]);

        $subscription = $account->fresh('subscription')->subscription;
        if ($subscription?->isPastDue()) {
            return redirect()->route('app.billing.index', [
                'tab' => 'plans',
                'checkout_plan' => $subscription->plan?->key,
            ])
                ->with('error', $subscription->last_error ?: 'Payment is required before using this workspace.');
        }

        return redirect()->route('app.dashboard')->with('success', 'Workspace created successfully.');
    }

    public function destroyWorkspace(Request $request, Account $account): RedirectResponse
    {
        $user = $request->user();

        if ($user?->isSuperAdmin() && ! $request->session()->has('impersonator_id')) {
            abort(403, 'Platform admins cannot delete user workspaces here.');
        }

        abort_unless($this->roleFor($request, $account) === 'owner', 403, 'Only the workspace owner can delete this workspace.');

        $validated = $request->validate([
            'confirmation_name' => ['required', 'string', 'max:255'],
        ]);

        if (! hash_equals($account->name, $validated['confirmation_name'])) {
            return back()
                ->withErrors(['confirmation_name' => 'Type the workspace name exactly to confirm deletion.'])
                ->with('error', 'Workspace name confirmation did not match.');
        }

        $accessibleAccounts = $user->accounts()
            ->select('accounts.id')
            ->get()
            ->merge($user->ownedAccounts()->select('id')->get())
            ->unique('id')
            ->values();

        if ($accessibleAccounts->count() <= 1) {
            return back()
                ->withErrors(['workspace' => 'You cannot delete your only workspace. Create or join another workspace first.'])
                ->with('error', 'You cannot delete your only workspace.');
        }

        $replacementAccountId = $accessibleAccounts
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->first(fn (int $id) => ! account_ids_match($id, $account->id));

        $deletedAccount = [
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'owner_id' => $account->owner_id,
        ];
        $logoPath = $account->logo_path;

        DB::transaction(function () use ($request, $user, $account, $deletedAccount) {
            app(AppNotificationService::class)->auditDestructive(
                'workspace_deleted',
                "Workspace {$deletedAccount['name']} deleted",
                $user,
                $account,
                $account,
                $deletedAccount,
                $request
            );

            $account->delete();
        });

        if ($logoPath) {
            Storage::disk('public')->delete($logoPath);
        }

        if (account_ids_match($request->session()->get('current_account_id'), $deletedAccount['id'])) {
            $request->session()->put('current_account_id', $replacementAccountId);
        }

        return redirect()
            ->route('app.workspaces.index')
            ->with('success', 'Workspace deleted successfully.');
    }

    /**
     * Switch to a different account.
     */
    public function switch(Request $request, Account $account)
    {
        $user = Auth::user();

        // Verify user has access (query instead of loading full users collection)
        $isOwner = account_ids_match($account->owner_id, $user->id);
        $isMember = $account->users()->where('user_id', $user->id)->exists();
        if (! $isOwner && ! $isMember) {
            abort(403, 'You do not have access to this account');
        }

        session(['current_account_id' => $account->id]);

        return redirect()->route('app.dashboard');
    }

    /**
     * Show modules management page.
     * Shows all modules available on the account's plan with enable/disable options.
     */
    public function modules(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        $planResolver = app(\App\Core\Billing\PlanResolver::class);
        $plan = $planResolver->getAccountPlan($account);
        $availableModuleKeys = $planResolver->getAvailableModuleKeys($account);

        // Get all modules from database that are enabled at platform level
        $allModules = \App\Models\Module::where('is_enabled', true)->get();

        // Get account module toggles
        $accountModules = \App\Models\AccountModule::where('account_id', $account->id)
            ->get()
            ->keyBy('module_key');

        // Get current plan info
        $currentPlan = $plan ? [
            'key' => $plan->key,
            'name' => $plan->name] : null;

        $defaultPhone = WhatsAppConnection::where('account_id', $account->id)
            ->where('is_active', true)
            ->whereNotNull('business_phone')
            ->value('business_phone');

        return Inertia::render('App/Modules', [
            'account' => $account,
            'current_plan' => $currentPlan,
            'default_phone' => $defaultPhone,
            'modules' => $allModules->map(function ($module) use ($accountModules, $availableModuleKeys, $plan) {
                $accountModule = $accountModules->get($module->key);
                $isInPlan = in_array($module->key, $plan?->modules ?? [], true);
                $isCore = $module->is_core;
                $isAvailable = in_array($module->key, $availableModuleKeys, true);
                $enabled = $accountModule ? (bool) $accountModule->enabled : $isAvailable;

                return [
                    'id' => $module->id,
                    'key' => $module->key,
                    'name' => $module->name,
                    'description' => $module->description,
                    'is_core' => $isCore,
                    'is_in_plan' => $isInPlan,
                    'enabled' => $enabled,
                    'available' => $isAvailable,
                    'can_toggle' => $isAvailable, // Can toggle if available (in plan or core)
                ];
            })->filter(function ($module) {
                // Only show modules that are available (in plan) or core
                return $module['available'];
            })->sortBy(function ($module) {
                // Sort: core modules first, then plan modules, then by name
                if ($module['is_core']) {
                    return '0-'.$module['name'];
                }
                if ($module['is_in_plan']) {
                    return '1-'.$module['name'];
                }

                return '2-'.$module['name'];
            })->values()]);
    }

    protected function authorizeWorkspaceManagement(Request $request, ?Account $account): void
    {
        if (! $account) {
            abort(404, 'Workspace not found.');
        }

        if ($request->user()?->isSuperAdmin() && ! $request->session()->has('impersonator_id')) {
            abort(403, 'Platform admins do not manage workspace settings here.');
        }

        $role = $this->roleFor($request, $account);
        if (! in_array($role, ['owner', 'admin'], true)) {
            abort(403, 'Only workspace owners and admins can update workspace settings.');
        }
    }

    protected function roleFor(Request $request, Account $account): ?string
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ((int) $account->owner_id === (int) $user->id || $account->isOwnedBy($user)) {
            return 'owner';
        }

        return $account->users()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot
            ?->role;
    }

    protected function teamMemberCount(Account $account): int
    {
        $ids = $account->relationLoaded('users')
            ? $account->users->pluck('id')
            : $account->users()->pluck('users.id');

        if ($account->owner_id) {
            $ids->push($account->owner_id);
        }

        return $ids->filter()->unique()->count();
    }

    /**
     * Toggle module enable/disable status.
     */
    public function toggleModule(Request $request)
    {
        // Get moduleKey from route parameter directly to avoid binding issues
        $moduleKey = $request->route('moduleKey');

        if (! $moduleKey) {
            \Log::error('Module key not found in route', [
                'route_params' => $request->route()->parameters(),
                'path' => $request->path()]);
            abort(404, 'Module key not found in route.');
        }

        \Log::info('toggleModule called', [
            'moduleKey_param' => $moduleKey,
            'route_params' => $request->route()->parameters(),
            'account_slug' => $request->route('account')]);

        $account = $request->attributes->get('account') ?? current_account();

        if (! $account) {
            \Log::error('Account not found in toggleModule', [
                'account_slug' => $request->route('account')]);
            abort(404, 'Account not found.');
        }

        $planResolver = app(\App\Core\Billing\PlanResolver::class);
        $availableModuleKeys = $planResolver->getAvailableModuleKeys($account);

        $module = \App\Models\Module::where('key', $moduleKey)->first();
        if (! $module) {
            \Log::error('Module not found', [
                'moduleKey' => $moduleKey]);
            abort(404, 'Module not found.');
        }

        // Check if module is enabled at platform level
        if (! $module->is_enabled) {
            abort(403, 'This module is currently disabled at the platform level. Please contact support.');
        }

        if (! in_array($moduleKey, $availableModuleKeys, true)) {
            abort(403, 'This module is not available on your current plan.');
        }

        $accountModule = \App\Models\AccountModule::where('account_id', $account->id)
            ->where('module_key', $moduleKey)
            ->first();

        $currentEnabled = $accountModule ? (bool) $accountModule->enabled : true;

        $accountModule = \App\Models\AccountModule::updateOrCreate(
            ['account_id' => $account->id, 'module_key' => $moduleKey],
            ['enabled' => ! $currentEnabled]
        );

        \Log::info('AccountModule found/created', [
            'account_id' => $account->id,
            'module_key' => $moduleKey,
            'enabled' => $accountModule->enabled]);

        // Toggle enabled status
        $accountModule->enabled = ! $accountModule->enabled;
        $accountModule->save();

        return back()->with('success', "Module {$module->name} ".($accountModule->enabled ? 'enabled' : 'disabled').' successfully.');
    }
}
