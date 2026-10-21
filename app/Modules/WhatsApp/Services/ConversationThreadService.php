<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppConversationNote;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Support\CustomerMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the message thread shown in the inbox. Shared by the first page render (Inertia props)
 * and the polling endpoint so both return exactly the same shape.
 */
class ConversationThreadService
{
    public const MESSAGE_BATCH_SIZE = 200;

    public const NOTE_BATCH_SIZE = 50;

    public const AUDIT_BATCH_SIZE = 50;

    /**
     * Latest slice of the thread, oldest first, ready for the inbox to render straight away.
     */
    public function initialThread(WhatsAppConversation $conversation): array
    {
        $messages = $this->latestMessages($conversation);

        return [
            'conversation_id' => $conversation->id,
            'server_time' => now()->toIso8601String(),
            'messages' => $messages->all(),
            'notes' => $this->notesAfter($conversation, 0)->all(),
            'audit_events' => $this->auditEventsAfter($conversation, 0)->all(),
            'history_truncated' => $this->historyTruncated($conversation, $messages->count()),
            'batch_size' => self::MESSAGE_BATCH_SIZE,
        ];
    }

    public function latestMessages(WhatsAppConversation $conversation, int $limit = self::MESSAGE_BATCH_SIZE): Collection
    {
        return WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (WhatsAppMessage $message) => $this->messagePayload($message))
            ->values();
    }

    public function messagesAfter(WhatsAppConversation $conversation, int $afterMessageId, int $limit = self::MESSAGE_BATCH_SIZE): Collection
    {
        if ($afterMessageId <= 0) {
            return $this->latestMessages($conversation, $limit);
        }

        return WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->where('id', '>', $afterMessageId)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get()
            ->map(fn (WhatsAppMessage $message) => $this->messagePayload($message))
            ->values();
    }

    public function historyTruncated(WhatsAppConversation $conversation, int $loadedCount): bool
    {
        if ($loadedCount < self::MESSAGE_BATCH_SIZE) {
            return false;
        }

        return WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)->count() > $loadedCount;
    }

    /**
     * Notes newer than the cursor. With no cursor, the latest batch (oldest first).
     */
    public function notesAfter(WhatsAppConversation $conversation, int $afterNoteId): Collection
    {
        $query = WhatsAppConversationNote::where('whatsapp_conversation_id', $conversation->id)
            ->with('creator:id,name,email');

        $notes = $afterNoteId > 0
            ? $query->where('id', '>', $afterNoteId)->orderBy('id')->limit(self::NOTE_BATCH_SIZE)->get()
            : $query->orderByDesc('id')->limit(self::NOTE_BATCH_SIZE)->get()->reverse();

        return $notes->map(fn ($note) => [
            'id' => $note->id,
            'note' => $note->note,
            'created_at' => $note->created_at->toIso8601String(),
            'created_by' => $note->creator ? [
                'id' => $note->creator->id,
                'name' => $note->creator->name,
                'email' => $note->creator->email,
            ] : null,
        ])->values();
    }

    /**
     * Timeline events newer than the cursor. With no cursor, the latest batch (oldest first).
     */
    public function auditEventsAfter(WhatsAppConversation $conversation, int $afterAuditId): Collection
    {
        $query = WhatsAppConversationAuditEvent::where('whatsapp_conversation_id', $conversation->id)
            ->with('actor:id,name,email');

        $events = $afterAuditId > 0
            ? $query->where('id', '>', $afterAuditId)->orderBy('id')->limit(self::AUDIT_BATCH_SIZE)->get()
            : $query->orderByDesc('id')->limit(self::AUDIT_BATCH_SIZE)->get()->reverse();

        return $events->map(fn ($event) => [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'description' => $event->description,
            'meta' => $event->meta,
            'created_at' => $event->created_at->toIso8601String(),
            'actor' => $event->actor ? [
                'id' => $event->actor->id,
                'name' => $event->actor->name,
                'email' => $event->actor->email,
            ] : null,
        ])->values();
    }

    /**
     * Received / sent message counts per conversation, in one grouped query.
     * Reactions are not counted as messages.
     *
     * @param  array<int>  $conversationIds
     * @return array<int, array{received: int, sent: int, total: int}>
     */
    public function messageCounts(array $conversationIds): array
    {
        $conversationIds = array_values(array_unique(array_filter(array_map('intval', $conversationIds))));
        if ($conversationIds === []) {
            return [];
        }

        $rows = DB::table('whatsapp_messages')
            ->whereIn('whatsapp_conversation_id', $conversationIds)
            ->whereNotIn('type', WhatsAppMessage::UNCOUNTED_TYPES)
            ->groupBy('whatsapp_conversation_id')
            ->selectRaw("whatsapp_conversation_id, SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as received, SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as sent")
            ->get()
            ->keyBy('whatsapp_conversation_id');

        $counts = [];
        foreach ($conversationIds as $id) {
            $row = $rows->get($id);
            $received = (int) ($row->received ?? 0);
            $sent = (int) ($row->sent ?? 0);
            $counts[$id] = ['received' => $received, 'sent' => $sent, 'total' => $received + $sent];
        }

        return $counts;
    }

    public function messagePayload(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'type' => $message->type,
            'text_body' => $message->text_body,
            'payload' => $message->payload,
            'status' => $message->status,
            'meta_message_id' => $message->meta_message_id,
            'created_at' => $message->created_at->toIso8601String(),
            'updated_at' => $message->updated_at?->toIso8601String(),
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'error_message' => $message->status === 'failed'
                ? CustomerMessage::humanize($message->error_message ?: 'This message could not be delivered.', 'This message could not be delivered.')
                : null,
        ];
    }
}
