<?php

namespace App\Modules\WhatsApp\Policies;

use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;

class WhatsAppConnectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All account members can view connections
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WhatsAppConnection $whatsAppConnection): bool
    {
        // User must be a member of the account
        return $whatsAppConnection->account->users->contains($user) ||
               (int) $whatsAppConnection->account->owner_id === (int) $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only owners and admins can create connections
        $account = current_account();
        if (! $account) {
            return false;
        }

        $membership = $account->users()->where('user_id', $user->id)->first();
        if ($membership) {
            return in_array($membership->pivot->role, ['owner', 'admin']);
        }

        return (int) $account->owner_id === (int) $user->id;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WhatsAppConnection $whatsAppConnection): bool
    {
        // Only owners and admins can update connections
        $account = $whatsAppConnection->account;
        $membership = $account->users()->where('user_id', $user->id)->first();

        if ($membership) {
            return in_array($membership->pivot->role, ['owner', 'admin']);
        }

        return (int) $account->owner_id === (int) $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WhatsAppConnection $whatsAppConnection): bool
    {
        // Only owners and admins can delete connections
        return $this->update($user, $whatsAppConnection);
    }
}
