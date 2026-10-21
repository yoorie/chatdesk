<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountRole;
use App\Models\User;

class WorkspacePermissionService
{
    /** Resolve a page's permissions with one membership and at most one role query. */
    public function permissionsFor(User $user, Account $account, ?array $permissions = null): array
    {
        $permissions ??= array_keys(AccountRole::PERMISSIONS);
        if ($user->isSuperAdmin() || (int) $account->owner_id === (int) $user->id) {
            return array_fill_keys($permissions, true);
        }

        $role = $account->users()->where('user_id', $user->id)->value('account_users.role');
        $granted = [];
        if ($role && ! in_array($role, ['owner', 'admin', 'member'], true)) {
            $granted = AccountRole::where('account_id', $account->id)->where('key', $role)->value('permissions') ?? [];
            if (is_string($granted)) {
                $granted = json_decode($granted, true) ?: [];
            }
        }

        $result = [];
        foreach ($permissions as $permission) {
            $result[$permission] = match ($role) {
                'owner' => true,
                'admin' => ! str_ends_with($permission, '.owner'),
                'member', null => in_array($permission, ['inbox', 'contacts.view'], true),
                default => in_array($permission, $granted, true)
                    || in_array(str($permission)->before('.')->toString(), $granted, true),
            };
        }

        return $result;
    }

    public function can(User $user, Account $account, string $permission): bool
    {
        return $this->permissionsFor($user, $account, [$permission])[$permission];
    }

    public function assert(User $user, Account $account, string $permission): void
    {
        abort_unless($this->can($user, $account, $permission), 403, 'Your role does not have permission for this action.');
    }

    public function assertOwner(User $user, Account $account): void
    {
        abort_unless($user->isSuperAdmin() || (int) $account->owner_id === (int) $user->id, 403, 'Only the workspace owner can perform this action.');
    }
}
