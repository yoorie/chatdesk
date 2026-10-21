<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    /**
     * Display unified settings page.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();
        $user = $request->user();

        return Inertia::render('Settings/Index', \App\Support\Demo::mask([
            'account' => $account,
            'workspace' => $account ? [
                'id' => $account->id,
                'name' => $account->name,
                'slug' => $account->slug,
                'workspace_type' => $account->workspace_type ?: 'business',
                'workspace_type_label' => $account->workspace_type_label,
                'industry' => $account->industry,
                'timezone' => $account->timezone ?: config('app.timezone', 'UTC'),
                'logo_url' => $account->logo_path ? Storage::disk('public')->url($account->logo_path) : null,
                'status' => $account->status,
                'billing_name' => $account->billing_name,
                'billing_email' => $account->billing_email,
                'billing_gstin' => $account->billing_gstin,
                'billing_address_line1' => $account->billing_address_line1,
                'billing_address_line2' => $account->billing_address_line2,
                'billing_city' => $account->billing_city,
                'billing_state' => $account->billing_state,
                'billing_state_code' => $account->billing_state_code,
                'billing_postal_code' => $account->billing_postal_code,
                'billing_country' => $account->billing_country ?: 'IN',
            ] : null,
            'workspaceTypes' => Account::workspaceTypes(),
            'timezones' => timezone_identifiers_list(),
            'initialTab' => in_array($request->query('tab'), ['workspace', 'profile', 'security', 'billing', 'notifications', 'inbox'], true)
                ? $request->query('tab')
                : 'workspace',
            'auth' => [
                'user' => $user,
            ],
            'security' => [
                'sessions' => $this->sessionPayload($request),
                'two_factor_setup' => $this->twoFactorSetupPayload($user),
            ],
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail,
        ]));
    }

    /**
     * Update workspace profile from the single settings page.
     */
    public function updateWorkspace(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! $account) {
            abort(404);
        }

        if ($request->user()?->isSuperAdmin() && ! $request->session()->has('impersonator_id')) {
            abort(403, 'Platform admins do not manage workspace settings here.');
        }

        $isOwner = (int) $account->owner_id === (int) $request->user()->id;
        $role = $account->users()->where('user_id', $request->user()->id)->first()?->pivot?->role;

        if (! $isOwner && ! in_array($role, ['owner', 'admin'], true)) {
            abort(403, 'Only workspace owners and admins can update workspace settings.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'workspace_type' => ['required', 'string', 'in:business,agency,client,branch,project'],
            'industry' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_gstin' => ['nullable', 'string', 'max:20'],
            'billing_address_line1' => ['nullable', 'string', 'max:255'],
            'billing_address_line2' => ['nullable', 'string', 'max:255'],
            'billing_city' => ['nullable', 'string', 'max:120'],
            'billing_state' => ['nullable', 'string', 'max:120'],
            'billing_state_code' => ['nullable', 'string', 'max:8'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'max:2'],
        ]);

        $logoPath = $account->logo_path;

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')->store('workspaces/logos', 'public');
        }

        $account->update([
            'name' => $validated['name'],
            'workspace_type' => $validated['workspace_type'],
            'industry' => $validated['industry'] ?? null,
            'timezone' => $validated['timezone'],
            'logo_path' => $logoPath,
            'billing_name' => $validated['billing_name'] ?? null,
            'billing_email' => $validated['billing_email'] ?? null,
            'billing_gstin' => strtoupper((string) ($validated['billing_gstin'] ?? '')) ?: null,
            'billing_address_line1' => $validated['billing_address_line1'] ?? null,
            'billing_address_line2' => $validated['billing_address_line2'] ?? null,
            'billing_city' => $validated['billing_city'] ?? null,
            'billing_state' => $validated['billing_state'] ?? null,
            'billing_state_code' => strtoupper((string) ($validated['billing_state_code'] ?? '')) ?: null,
            'billing_postal_code' => $validated['billing_postal_code'] ?? null,
            'billing_country' => strtoupper((string) ($validated['billing_country'] ?? 'IN')),
        ]);

        return back()->with('success', 'Workspace settings updated.');
    }

    /**
     * Update inbox settings.
     */
    public function updateInbox(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $validated = $request->validate([
            'auto_assign_enabled' => 'required|boolean',
            'auto_assign_strategy' => 'required|in:round_robin',
            'welcome_message_enabled' => 'required|boolean',
            'welcome_message_body' => 'nullable|string|max:1024',
            'auto_close_conversations_enabled' => 'required|boolean',
            'auto_close_after_hours' => 'nullable|integer|min:1|max:720',
        ]);

        if ($validated['welcome_message_enabled'] && blank($validated['welcome_message_body'] ?? null)) {
            Validator::make([], [])->after(function ($validator) {
                $validator->errors()->add('welcome_message_body', 'Enter a welcome message before enabling automation.');
            })->validate();
        }

        $account->update([
            'auto_assign_enabled' => $validated['auto_assign_enabled'],
            'auto_assign_strategy' => $validated['auto_assign_strategy'],
            'welcome_message_enabled' => $validated['welcome_message_enabled'],
            'welcome_message_body' => $validated['welcome_message_body'] ?? null,
            'auto_close_conversations_enabled' => $validated['auto_close_conversations_enabled'],
            'auto_close_after_hours' => $validated['auto_close_after_hours'] ?? 48,
        ]);

        return back()->with('success', 'Inbox settings updated.');
    }

    /**
     * Update notification preferences.
     */
    public function updateNotifications(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'notify_assignment_enabled' => 'required|boolean',
            'notify_mention_enabled' => 'required|boolean',
            'notify_sound_enabled' => 'required|boolean',
            'notify_billing_enabled' => 'required|boolean',
            'notify_waba_enabled' => 'required|boolean',
            'notify_automation_enabled' => 'required|boolean',
            'notify_leads_enabled' => 'required|boolean',
            'notify_templates_enabled' => 'required|boolean',
            'notify_email_enabled' => 'required|boolean',
            'notify_in_app_enabled' => 'required|boolean',
            'quiet_hours_enabled' => 'required|boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
        ]);

        $user->update($validated);

        return back()->with('success', 'Notification preferences updated.');
    }

    private function sessionPayload(Request $request): array
    {
        if (config('session.driver') !== 'database' || ! \Illuminate\Support\Facades\Schema::hasTable(config('session.table', 'sessions'))) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => hash_equals((string) $session->id, (string) $request->session()->getId()),
            ])
            ->all();
    }

    private function twoFactorSetupPayload(?\App\Models\User $user): ?array
    {
        if (! $user?->two_factor_secret || $user->two_factor_enabled_at) {
            return null;
        }

        $secret = decrypt($user->two_factor_secret);
        $issuer = config('app.name', 'ChatDesk');

        return [
            'secret' => $secret,
            'otpauth_url' => app(\App\Services\TwoFactorTotpService::class)->otpauthUrl($issuer, $user->email, $secret),
        ];
    }
}
