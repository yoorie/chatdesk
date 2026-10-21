<?php

namespace App\Modules\Chatbots\Policies;

use App\Models\Account;
use App\Models\User;
use App\Modules\Chatbots\Models\Bot;

class ChatbotPolicy
{
    /**
     * Resolve account from Gate arguments. Controllers call Gate::authorize('viewAny', [Bot::class, $account]).
     */
    protected function account(User $user, mixed ...$args): ?Account
    {
        foreach ($args as $arg) {
            if ($arg instanceof Account) {
                return $arg;
            }
        }

        return null;
    }

    /**
     * Determine if user can view bots.
     */
    public function viewAny(User $user, mixed ...$args): bool
    {
        $account = $this->account($user, ...$args);
        if (! $account) {
            return false;
        }

        return (int) $account->owner_id === (int) $user->id ||
               $account->users()->where('user_id', $user->id)->where('role', 'admin')->exists();
    }

    /**
     * Determine if user can manage bots.
     */
    public function manage(User $user, mixed ...$args): bool
    {
        $account = $this->account($user, ...$args);
        if (! $account) {
            return false;
        }

        return (int) $account->owner_id === (int) $user->id ||
               $account->users()->where('user_id', $user->id)->where('role', 'admin')->exists();
    }

    /**
     * Determine if user can run test.
     */
    public function runTest(User $user, mixed ...$args): bool
    {
        return $this->manage($user, ...$args);
    }
}
