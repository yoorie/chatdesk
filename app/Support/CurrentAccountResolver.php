<?php

namespace App\Support;

use App\Models\Account;
use App\Models\User;

/**
 * Decides which workspace a signed-in user is working in.
 *
 * The session remembers the last workspace, but that value can be missing or stale
 * (session regenerated at login, concurrent requests racing on the session row, a
 * workspace that was deleted or the user was removed from, impersonation leftovers).
 * A stale value must never send someone with a workspace to onboarding, so we always
 * fall back to the user's own memberships, preferring active workspaces.
 */
class CurrentAccountResolver
{
    public function resolve(User $user, mixed $preferredAccountId = null): ?Account
    {
        if ($preferredAccountId !== null && $preferredAccountId !== '' && is_numeric($preferredAccountId)) {
            $account = Account::find((int) $preferredAccountId);
            if ($account && $user->canAccessAccount($account)) {
                return $account;
            }
        }

        return $this->defaultAccountFor($user);
    }

    /**
     * Active workspaces first, then owned before membership, then oldest first.
     */
    public function defaultAccountFor(User $user): ?Account
    {
        $userId = (int) $user->id;

        return Account::query()
            ->where(function ($query) use ($userId) {
                $query->where('owner_id', $userId)
                    ->orWhereIn('id', function ($sub) use ($userId) {
                        $sub->select('account_id')->from('account_users')->where('user_id', $userId);
                    });
            })
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN owner_id = ? THEN 0 ELSE 1 END', [$userId])
            ->orderBy('id')
            ->first();
    }

    public function hasAnyAccount(User $user): bool
    {
        return $this->defaultAccountFor($user) !== null;
    }
}
