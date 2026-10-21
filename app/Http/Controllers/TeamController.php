<?php

namespace App\Http\Controllers;

use App\Mail\AccountInvitationMail;
use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\AccountRole;
use App\Models\AccountUser;
use App\Models\User;
use App\Services\MailDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function __construct(
        protected MailDeliveryService $mailDeliveryService
    ) {}

    /**
     * Use direct SMTP for invite flows when default mailer is failover.
     * This avoids false-positive "sent" states when fallback mailers swallow SMTP issues.
     */
    private function resolveInviteMailer(): string
    {
        $defaultMailer = (string) config('mail.default', 'smtp');
        $mailers = (array) config('mail.mailers', []);

        if ($defaultMailer === 'failover' && array_key_exists('smtp', $mailers)) {
            return 'smtp';
        }

        return $defaultMailer;
    }

    /**
     * Check if user can view team.
     */
    private function canViewTeam(User $user, Account $account): bool
    {
        if ((int) $account->owner_id === (int) $user->id) {
            return true;
        }

        $accountUser = AccountUser::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        return (bool) $accountUser;
    }

    /**
     * Check if user can manage team.
     */
    private function canManageTeam(User $user, Account $account): bool
    {
        if ((int) $account->owner_id === (int) $user->id) {
            return true;
        }

        $accountUser = AccountUser::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $accountUser) {
            return false;
        }

        return $this->roleHasPermission($account, $accountUser->role, 'team')
            || $this->roleHasPermission($account, $accountUser->role, 'roles');
    }

    private function canManageRoles(User $user, Account $account): bool
    {
        if ((int) $account->owner_id === (int) $user->id) {
            return true;
        }

        $accountUser = AccountUser::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        return $accountUser
            ? $this->roleHasPermission($account, $accountUser->role, 'roles')
            : false;
    }

    private function roleHasPermission(Account $account, ?string $role, string $permission): bool
    {
        if ($role === 'owner' || $role === 'admin') {
            return true;
        }

        if ($role === 'member' || ! $role) {
            return $permission === 'inbox';
        }

        $customRole = AccountRole::where('account_id', $account->id)
            ->where('key', $role)
            ->first();

        return $customRole
            ? in_array($permission, $customRole->permissions ?? [], true)
            : false;
    }

    private function roleDefinitions(Account $account): array
    {
        $customRoles = AccountRole::where('account_id', $account->id)
            ->orderBy('name')
            ->get()
            ->map(function (AccountRole $role) use ($account) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'key' => $role->key,
                    'description' => $role->description,
                    'permissions' => $role->permissions ?? [],
                    'is_system' => false,
                    'is_owner' => false,
                    'members_count' => $account->users()
                        ->where('users.is_platform_admin', false)
                        ->wherePivot('role', $role->key)
                        ->count(),
                    'pending_invites_count' => AccountInvitation::where('account_id', $account->id)
                        ->where('role', $role->key)
                        ->whereNull('accepted_at')
                        ->count(),
                ];
            })
            ->all();

        return array_merge(AccountRole::defaultRoles($account), $customRoles);
    }

    private function assignableRoleKeys(Account $account): array
    {
        return collect($this->roleDefinitions($account))
            ->reject(fn ($role) => (bool) ($role['is_owner'] ?? false))
            ->pluck('key')
            ->values()
            ->all();
    }

    /**
     * Display team members.
     */
    public function index(Request $request): Response
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account) {
            abort(404, 'Account not found.');
        }
        $user = $request->user();

        // Check if user can view team
        if (! $this->canViewTeam($user, $account)) {
            abort(403, 'You do not have permission to view team members.');
        }

        // Get all members including owner
        $members = collect();

        // Add owner
        if ($account->owner && ! $account->owner->isSuperAdmin()) {
            $members->push([
                'id' => $account->owner->id,
                'name' => $account->owner->name,
                'email' => $account->owner->email,
                'role' => 'owner',
                'joined_at' => $account->created_at->toIso8601String(),
                'is_owner' => true]);
        }

        // Add account users (exclude owner to avoid duplicates)
        $accountUsersQuery = AccountUser::where('account_id', $account->id)
            ->whereHas('user', fn ($query) => $query->where('is_platform_admin', false))
            ->with('user');

        // Exclude owner if owner_id exists
        if ($account->owner_id) {
            $accountUsersQuery->where('user_id', '!=', $account->owner_id);
        }

        $accountUsers = $accountUsersQuery->get()
            ->map(function ($accountUser) {
                return [
                    'id' => $accountUser->user->id,
                    'name' => $accountUser->user->name,
                    'email' => $accountUser->user->email,
                    'role' => $accountUser->role,
                    'joined_at' => $accountUser->created_at->toIso8601String(),
                    'is_owner' => false,
                    'account_user_id' => $accountUser->id];
            });

        $members = $members->merge($accountUsers);

        $currentUser = $request->user();
        $canManage = $this->canManageTeam($currentUser, $account);

        $pendingInvites = AccountInvitation::where('account_id', $account->id)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AccountInvitation $invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'invited_at' => $invitation->created_at?->toIso8601String(),
                    'expires_at' => $invitation->expires_at?->toIso8601String()];
            });

        return Inertia::render('App/Team/Index', [
            'account' => $account,
            'members' => $members->values(),
            'can_manage' => $canManage,
            'can_manage_roles' => $this->canManageRoles($currentUser, $account),
            'current_user_id' => $currentUser->id,
            'pending_invites' => $pendingInvites->values(),
            'roles' => $this->roleDefinitions($account),
            'permissions' => collect(AccountRole::PERMISSIONS)
                ->map(fn ($label, $key) => [
                    'key' => $key,
                    'label' => $label,
                    'group' => str_contains($key, '.') ? str($key)->before('.')->headline()->toString() : 'Core',
                    'owner_only' => str_ends_with($key, '.owner'),
                    'sensitive' => in_array($key, ['chats.delete', 'payments.approve', 'contacts.export', 'api_keys.owner'], true),
                ])
                ->values(),
        ]);
    }

    /**
     * Invite a new member.
     */
    public function invite(Request $request): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account) {
            abort(404, 'Account not found.');
        }
        $user = $request->user();

        if (! $this->canManageTeam($user, $account)) {
            abort(403, 'You do not have permission to manage team members.');
        }

        $request->validate([
            'email' => 'required|email:rfc,dns',
            'role' => ['nullable', Rule::in($this->assignableRoleKeys($account))]]);

        $inviteEmail = strtolower(trim($request->email));
        $inviteRole = $request->input('role') ?: 'member';
        $existingUser = User::where('email', $inviteEmail)->first();

        if ($existingUser && $existingUser->isSuperAdmin()) {
            return back()->with('error', 'You cannot add the platform admin user to a account.');
        }

        // Check if user is already a member
        if ($existingUser && $account->users()->where('user_id', $existingUser->id)->exists()) {
            return back()->with('error', 'User is already a member of this account.');
        }

        // Check if user is the owner
        if ($existingUser && (int) $account->owner_id === (int) $existingUser->id) {
            return back()->with('error', 'User is already the owner of this account.');
        }

        if (false) {
            return back()->with('error', 'Your plan team limit has been reached. Upgrade to add more members.');
        }

        if ($existingUser) {
            // Enforce single-team membership for agents:
            // move user from any previous account memberships before adding here.
            $ownsAnotherAccount = Account::query()
                ->where('owner_id', $existingUser->id)
                ->where('id', '!=', $account->id)
                ->exists();

            if ($ownsAnotherAccount) {
                return back()->with('error', 'This user owns another account and cannot be auto-moved. Transfer ownership first.');
            }

            $removedMemberships = AccountUser::query()
                ->where('user_id', $existingUser->id)
                ->where('account_id', '!=', $account->id)
                ->delete();

            $account->users()->attach($existingUser->id, [
                'role' => $inviteRole]);

            $message = $removedMemberships > 0
                ? 'Member moved from previous team and added successfully.'
                : 'Member added successfully.';

            return back()->with('success', $message);
        }

        AccountInvitation::where('account_id', $account->id)
            ->where('email', $inviteEmail)
            ->whereNull('accepted_at')
            ->delete();

        $invitation = AccountInvitation::create([
            'account_id' => $account->id,
            'invited_by' => $user->id,
            'email' => $inviteEmail,
            'role' => $inviteRole,
            'token' => AccountInvitation::generateToken(),
            'expires_at' => now()->addDays(7)]);

        $inviteUrl = route('register', [
            'invite' => $invitation->token,
            'email' => $invitation->email,
        ]);

        try {
            // Send synchronously so invite delivery does not depend on queue workers.
            $mailer = $this->resolveInviteMailer();
            $this->mailDeliveryService->sendMailable(
                recipient: $inviteEmail,
                mailable: new AccountInvitationMail($invitation),
                mailer: $mailer,
                account: $account,
                meta: [
                    'feature' => 'team_invite',
                    'invitation_id' => $invitation->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send account invitation email', [
                'email' => $inviteEmail,
                'account_id' => $account->id,
                'mailer' => $this->resolveInviteMailer(),
                'error' => $e->getMessage()]);

            return back()
                ->with('warning', 'Invitation created, but email could not be sent. Share the invite link manually.')
                ->with('info', $inviteUrl);
        }

        return back()->with('success', 'Invitation sent successfully.');
    }

    /**
     * Update member role.
     */
    public function updateRole(Request $request, $user): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account) {
            abort(404, 'Account not found.');
        }
        $currentUser = $request->user();

        // Resolve user if not already a User instance
        if (! $user instanceof User) {
            $user = User::findOrFail($user);
        }

        if (! $this->canManageTeam($currentUser, $account)) {
            abort(403, 'You do not have permission to manage team members.');
        }

        // Cannot change owner role
        if ((int) $account->owner_id === (int) $user->id) {
            return back()->with('error', 'Cannot change owner role.');
        }
        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Cannot change platform admin role.');
        }

        $request->validate([
            'role' => ['required', Rule::in($this->assignableRoleKeys($account))]]);

        if (! $account->users()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'User is not a member of this account.');
        }

        $account->users()->updateExistingPivot($user->id, [
            'role' => $request->role]);

        return back()->with('success', 'Member role updated successfully.');
    }

    public function storeRole(Request $request): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account) {
            abort(404, 'Account not found.');
        }

        if (! $this->canManageRoles($request->user(), $account)) {
            abort(403, 'You do not have permission to manage roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(AccountRole::PERMISSIONS))],
        ]);

        $baseKey = AccountRole::normalizeKey($validated['name']);
        if (in_array($baseKey, ['owner', 'admin', 'member'], true)) {
            $baseKey = 'custom_'.$baseKey;
        }

        $key = $baseKey;
        $counter = 2;
        while (AccountRole::where('account_id', $account->id)->where('key', $key)->exists()) {
            $key = $baseKey.'_'.$counter;
            $counter++;
        }

        AccountRole::create([
            'account_id' => $account->id,
            'name' => $validated['name'],
            'key' => $key,
            'description' => $validated['description'] ?? null,
            'permissions' => array_values(array_unique($validated['permissions'] ?? ['inbox'])),
            'is_system' => false,
        ]);

        return back()->with('success', 'Role created successfully.');
    }

    public function updateRoleDefinition(Request $request, AccountRole $role): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account || ! account_ids_match($role->account_id, $account->id)) {
            abort(404, 'Role not found.');
        }

        if (! $this->canManageRoles($request->user(), $account)) {
            abort(403, 'You do not have permission to manage roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(AccountRole::PERMISSIONS))],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => array_values(array_unique($validated['permissions'] ?? ['inbox'])),
        ]);

        return back()->with('success', 'Role updated successfully.');
    }

    public function deleteRole(Request $request, AccountRole $role): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account || ! account_ids_match($role->account_id, $account->id)) {
            abort(404, 'Role not found.');
        }

        if (! $this->canManageRoles($request->user(), $account)) {
            abort(403, 'You do not have permission to manage roles.');
        }

        $hasMembers = $account->users()
            ->where('users.is_platform_admin', false)
            ->wherePivot('role', $role->key)
            ->exists();
        $hasInvites = AccountInvitation::where('account_id', $account->id)
            ->where('role', $role->key)
            ->whereNull('accepted_at')
            ->exists();

        if ($hasMembers || $hasInvites) {
            return back()->with('error', 'Move members and revoke pending invites before deleting this role.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    /**
     * Remove a member.
     */
    public function remove(Request $request, $user)
    {
        $account = null;
        try {
            $account = $request->attributes->get('account') ?? current_account();
            if (! $account) {
                abort(404, 'Account not found.');
            }
            $currentUser = $request->user();

            // Resolve user if not already a User instance
            if (! $user instanceof User) {
                $userId = is_numeric($user) ? (int) $user : $user;
                $user = User::findOrFail($userId);
            }

            if (! $this->canManageTeam($currentUser, $account)) {
                abort(403, 'You do not have permission to manage team members.');
            }

            // Cannot remove owner
            if ((int) $account->owner_id === (int) $user->id) {
                return back()->with('error', 'Cannot remove account owner.');
            }
            if ($user->isSuperAdmin()) {
                return back()->with('error', 'Cannot remove platform admin from account.');
            }

            // Cannot remove yourself
            if ((int) $user->id === (int) $request->user()->id) {
                return back()->with('error', 'Cannot remove yourself from the account.');
            }

            if (! $account->users()->where('user_id', $user->id)->exists()) {
                return back()->with('error', 'User is not a member of this account.');
            }

            $account->users()->detach($user->id);

            // For Inertia requests, return a proper response
            if ($request->header('X-Inertia')) {
                return redirect()->route('app.team.index')
                    ->with('success', 'Member removed successfully.');
            }

            return back()->with('success', 'Member removed successfully.');
        } catch (\Exception $e) {
            Log::error('Team member removal failed', [
                'user_id' => $user instanceof User ? $user->id : $user,
                'account_id' => $account?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()]);

            return back()->with('error', 'We could not remove this member. Please try again.');
        }
    }

    /**
     * Revoke a pending invite.
     */
    public function revokeInvite(Request $request, AccountInvitation $invitation): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account) {
            abort(404, 'Account not found.');
        }
        $currentUser = $request->user();

        if (! $this->canManageTeam($currentUser, $account)) {
            abort(403, 'You do not have permission to manage team members.');
        }

        if (! account_ids_match($invitation->account_id, $account->id)) {
            abort(403, 'Invite does not belong to this account.');
        }

        if ($invitation->accepted_at) {
            return back()->with('error', 'Invite has already been accepted.');
        }

        $invitation->delete();

        return back()->with('success', 'Invitation revoked successfully.');
    }

    /**
     * Resend a pending invite.
     */
    public function resendInvite(Request $request, AccountInvitation $invitation): \Illuminate\Http\RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (! $account) {
            abort(404, 'Account not found.');
        }
        $currentUser = $request->user();

        if (! $this->canManageTeam($currentUser, $account)) {
            abort(403, 'You do not have permission to manage team members.');
        }

        if (! account_ids_match($invitation->account_id, $account->id)) {
            abort(403, 'Invite does not belong to this account.');
        }

        if ($invitation->accepted_at) {
            return back()->with('error', 'Invite has already been accepted.');
        }

        // Always refresh token and expiry on resend.
        $invitation->update([
            'token' => AccountInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        $invitation = $invitation->fresh();
        $inviteUrl = route('register', [
            'invite' => $invitation->token,
            'email' => $invitation->email,
        ]);

        try {
            // Send synchronously so resend result matches user feedback immediately.
            $mailer = $this->resolveInviteMailer();
            $this->mailDeliveryService->sendMailable(
                recipient: $invitation->email,
                mailable: new AccountInvitationMail($invitation),
                mailer: $mailer,
                account: $account,
                meta: [
                    'feature' => 'team_invite_resend',
                    'invitation_id' => $invitation->id,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to resend account invitation email', [
                'email' => $invitation->email,
                'account_id' => $account->id,
                'mailer' => $this->resolveInviteMailer(),
                'error' => $e->getMessage()]);

            return back()
                ->with('warning', 'Invite refreshed, but email could not be sent. Share the invite link manually.')
                ->with('info', $inviteUrl);
        }

        return back()->with('success', 'Invitation resent successfully.');
    }
}
