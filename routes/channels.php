<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
|--------------------------------------------------------------------------
| WhatsApp Inbox Channels
|--------------------------------------------------------------------------
|
| Private channels for account-scoped WhatsApp inbox realtime updates.
|
*/

// Account inbox channel - for conversation list updates
Broadcast::channel('account.{accountId}.whatsapp.inbox', function ($user, $accountId) {
    $account = \App\Models\Account::find($accountId);

    if (! $account) {
        \Log::debug('WhatsApp inbox channel auth denied: account not found', ['user_id' => $user->id, 'account_id' => $accountId]);

        return false;
    }

    if ($user->isPlatformAdmin()) {
        \Log::debug('WhatsApp inbox channel auth granted: platform admin', ['user_id' => $user->id, 'account_id' => $accountId]);

        return true;
    }

    // Owner always has access; otherwise check account_users pivot
    $isOwner = $account->owner_id && (int) $account->owner_id === (int) $user->id;
    $membership = $account->users()->where('user_id', $user->id)->first();

    if (! $isOwner && ! $membership) {
        \Log::debug('WhatsApp inbox channel auth denied: not owner or member', ['user_id' => $user->id, 'account_id' => $accountId]);

        return false;
    }

    \Log::debug('WhatsApp inbox channel auth granted', ['user_id' => $user->id, 'account_id' => $accountId, 'via' => $isOwner ? 'owner' : 'member']);

    // Return minimal user data for presence if needed
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// Conversation thread channel - for message updates within a conversation
Broadcast::channel('account.{accountId}.whatsapp.conversation.{conversationId}', function ($user, $accountId, $conversationId) {
    $account = \App\Models\Account::find($accountId);

    if (! $account) {
        \Log::debug('WhatsApp conversation channel auth denied: account not found', ['user_id' => $user->id, 'account_id' => $accountId]);

        return false;
    }

    if ($user->isPlatformAdmin()) {
        \Log::debug('WhatsApp conversation channel auth granted: platform admin', ['user_id' => $user->id, 'account_id' => $accountId, 'conversation_id' => $conversationId]);

        return true;
    }

    // Owner always has access; otherwise check account_users pivot
    $isOwner = $account->owner_id && (int) $account->owner_id === (int) $user->id;
    $membership = $account->users()->where('user_id', $user->id)->first();

    if (! $isOwner && ! $membership) {
        \Log::debug('WhatsApp conversation channel auth denied: not owner or member', ['user_id' => $user->id, 'account_id' => $accountId]);

        return false;
    }

    // Verify conversation belongs to this account
    $conversation = \App\Modules\WhatsApp\Models\WhatsAppConversation::find($conversationId);

    if (! $conversation || ! account_ids_match($conversation->account_id, $accountId)) {
        \Log::debug('WhatsApp conversation channel auth denied: conversation not found or wrong account', ['user_id' => $user->id, 'account_id' => $accountId, 'conversation_id' => $conversationId]);

        return false;
    }

    \Log::debug('WhatsApp conversation channel auth granted', ['user_id' => $user->id, 'account_id' => $accountId, 'conversation_id' => $conversationId, 'via' => $isOwner ? 'owner' : 'member']);

    // Return minimal user data
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

/*
|--------------------------------------------------------------------------
| Support Channels
|--------------------------------------------------------------------------
*/
