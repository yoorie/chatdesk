<?php

namespace App\Modules\WhatsApp\Events\Inbox;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public WhatsAppConversation $conversation
    ) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $accountId = $this->conversation->account_id;
        $conversationId = $this->conversation->id;

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
        return 'whatsapp.conversation.updated';
    }

    /**
     * Get the data to broadcast.
     * Include contact and connection so the inbox can show new conversations without a full refetch.
     */
    public function broadcastWith(): array
    {
        $conversation = $this->conversation;
        $conversation->loadMissing(['contact', 'connection']);

        $contactMeta = $conversation->contact?->metadata ?? [];
        $displayPhone = $conversation->contact
            ? ($conversation->contact->phone ?: $conversation->contact->wa_id)
            : null;

        $tags = [];
        if ($conversation->contact && $conversation->contact->relationLoaded('tags')) {
            $tags = $conversation->contact->tags?->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ];
            })->values()->toArray() ?? [];
        }

        $priority = null;
        if (Schema::hasColumn('whatsapp_conversations', 'priority')) {
            $priority = $conversation->priority;
        }

        $assigneeId = null;
        if (Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            $assigneeId = $conversation->assigned_to;
        }
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];

        return [
            'conversation' => [
                'id' => $conversation->id,
                'account_id' => $conversation->account_id,
                'status' => $conversation->status,
                'priority' => $priority,
                'assignee_id' => $assigneeId,
                'assigned_to' => $assigneeId,
                'tags' => $tags,
                'last_activity_at' => $conversation->last_message_at?->toIso8601String(),
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'last_inbound_message_at' => $conversation->messages()
                    ->where('direction', 'inbound')
                    ->latest('created_at')
                    ->first()?->created_at?->toIso8601String(),
                'last_message_preview' => $conversation->last_message_preview,
                'updated_at' => $conversation->updated_at?->toIso8601String(),
                'automation_processing' => $this->isProcessing($metadata),
                'automation_processing_mode' => $metadata['automation_processing_mode'] ?? null,
                'bot_paused' => (bool) ($metadata['bot_paused'] ?? false),
                'bot_paused_reason' => $metadata['bot_paused_reason'] ?? null,
                'handoff_status' => $metadata['handoff_status'] ?? null,
                'handoff_reason' => $metadata['handoff_reason'] ?? null,
                'contact' => $conversation->contact ? [
                    'id' => $conversation->contact->id,
                    'slug' => $conversation->contact->slug,
                    'wa_id' => $conversation->contact->wa_id,
                    'name' => $conversation->contact->name ?? $conversation->contact->wa_id,
                    'display_phone' => $displayPhone,
                    'email' => $conversation->contact->email,
                    'phone' => $conversation->contact->phone,
                    'company' => $conversation->contact->company,
                    'notes' => $conversation->contact->notes,
                    'status' => $conversation->contact->status,
                    'tags' => $tags,
                ] : null,
                'connection' => $conversation->connection ? [
                    'id' => $conversation->connection->id,
                    'name' => $conversation->connection->name,
                ] : null,
            ],
        ];
    }

    protected function isProcessing(array $metadata): bool
    {
        if (! ($metadata['automation_processing'] ?? false)) {
            return false;
        }

        $expiresAt = $metadata['automation_processing_expires_at'] ?? null;
        if (! $expiresAt) {
            return true;
        }

        return now()->lt(\Illuminate\Support\Carbon::parse($expiresAt));
    }
}
