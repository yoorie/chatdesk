<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\User;

class AccountPolicy
{
    /**
     * Determine if user can view team members.
     */
    public function viewTeam(User $user, Account $account): bool
    {
        // Owner and admins can view team
        if (account_ids_match($account->owner_id, $user->id)) {
            return true;
        }

        $accountUser = AccountUser::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        return $accountUser && in_array($accountUser->role, ['owner', 'admin']);
    }

    /**
     * Determine if user can manage team members.
     */
    public function manageTeam(User $user, Account $account): bool
    {
        // Only owner and admins can manage team
        if (account_ids_match($account->owner_id, $user->id)) {
            return true;
        }

        $accountUser = AccountUser::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        return $accountUser && $accountUser->role === 'admin';
    }

    /**
     * Determine if user can update account settings.
     */
    public function updateSettings(User $user, Account $account): bool
    {
        // Only owner and admins can update settings
        if (account_ids_match($account->owner_id, $user->id)) {
            return true;
        }

        $accountUser = AccountUser::where('account_id', $account->id)
            ->where('user_id', $user->id)
            ->first();

        return $accountUser && $accountUser->role === 'admin';
    }
}
