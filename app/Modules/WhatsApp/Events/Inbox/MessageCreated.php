<?php

namespace App\Modules\WhatsApp\Events\Inbox;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class MessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppMessage $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $accountId = $this->message->account_id;
        $conversationId = $this->message->whatsapp_conversation_id;

        return [
            // Account inbox channel (for list updates)
            new PrivateChannel("account.{$accountId}.whatsapp.inbox"),
            // Conversation channel (for thread updates)
            new PrivateChannel("account.{$accountId}.whatsapp.conversation.{$conversationId}")];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'whatsapp.message.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;
        $conversation?->loadMissing(['contact.tags:id,account_id,name,color', 'connection']);

        return [
            'account_id' => $this->message->account_id,
            'conversation_id' => $this->message->whatsapp_conversation_id,
            'message' => [
                'id' => $this->message->id,
                'direction' => $this->message->direction,
                'type' => $this->message->type,
                'text_body' => $this->message->text_body,
                'payload' => $this->message->payload,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at->toIso8601String(),
                'updated_at' => $this->message->updated_at?->toIso8601String(),
                'sent_at' => $this->message->sent_at?->toIso8601String(),
                'delivered_at' => $this->message->delivered_at?->toIso8601String(),
                'read_at' => $this->message->read_at?->toIso8601String(),
                'meta_message_id' => $this->message->meta_message_id],
            'contact' => $conversation?->contact ? [
                'id' => $conversation->contact->id,
                'wa_id' => $conversation->contact->wa_id,
                'name' => $conversation->contact->name] : null,
            'conversation' => $conversation ? [
                'id' => $conversation->id,
                'account_id' => $conversation->account_id,
                'status' => $conversation->status,
                'priority' => Schema::hasColumn('whatsapp_conversations', 'priority') ? $conversation->priority : null,
                'assignee_id' => Schema::hasColumn('whatsapp_conversations', 'assigned_to') ? $conversation->assigned_to : null,
                'assigned_to' => Schema::hasColumn('whatsapp_conversations', 'assigned_to') ? $conversation->assigned_to : null,
                'last_message_preview' => $conversation->last_message_preview ?: $this->message->text_body,
                'last_message_at' => ($conversation->last_message_at ?: $this->message->created_at)?->toIso8601String(),
                'last_inbound_message_at' => $this->message->direction === 'inbound'
                    ? $this->message->created_at->toIso8601String()
                    : $conversation->messages()
                        ->where('direction', 'inbound')
                        ->latest('created_at')
                        ->first()?->created_at?->toIso8601String(),
                'updated_at' => $conversation->updated_at?->toIso8601String(),
                'contact' => $conversation->contact ? [
                    'id' => $conversation->contact->id,
                    'wa_id' => $conversation->contact->wa_id,
                    'name' => $conversation->contact->name ?? $conversation->contact->wa_id,
                    'tags' => $conversation->contact->tags?->map(fn ($tag) => [
                        'id' => $tag->id,
                        'name' => $tag->name,
                        'color' => $tag->color,
                    ])->values()->toArray() ?? [],
                ] : null,
                'connection' => $conversation->connection ? [
                    'id' => $conversation->connection->id,
                    'name' => $conversation->connection->name,
                ] : null,
            ] : null];
    }
}
