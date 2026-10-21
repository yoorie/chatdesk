<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\ConversationThreadService;
use App\Models\WhatsAppCall;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class InboxStreamController extends Controller
{
    /**
     * Stream inbox updates (for polling fallback).
     */
    public function stream(Request $request)
    {
        $account = $request->attributes->get('account') ?? current_account();

        $since = $request->query('since');
        $sinceDate = $since ? Carbon::parse($since) : Carbon::now()->subMinutes(5);

        // Get conversations updated since timestamp
        $updatedConversations = WhatsAppConversation::where('account_id', $account->id)
            ->where(function ($query) use ($sinceDate) {
                $query->where('updated_at', '>', $sinceDate)
                    ->orWhere('last_message_at', '>', $sinceDate);
            })
            ->withMax(['messages as last_inbound_message_at' => function ($query) {
                $query->where('direction', 'inbound');
            }], 'created_at')
            ->with(['contact.tags:id,account_id,name,color', 'connection'])
            ->orderBy('last_message_at', 'desc')
            ->limit(50)
            ->get();
        $messageCounts = app(ConversationThreadService::class)->messageCounts($updatedConversations->pluck('id')->all());
        $updatedConversations = $updatedConversations
            ->map(function ($conversation) use ($messageCounts) {
                return [
                    'id' => $conversation->id,
                    'account_id' => $conversation->account_id,
                    'contact' => [
                        'id' => $conversation->contact->id,
                        'slug' => $conversation->contact->slug,
                        'wa_id' => $conversation->contact->wa_id,
                        'name' => $conversation->contact->name ?? $conversation->contact->wa_id,
                        'email' => $conversation->contact->email,
                        'phone' => $conversation->contact->phone,
                        'company' => $conversation->contact->company,
                        'notes' => $conversation->contact->notes,
                        'status' => $conversation->contact->status,
                        'source' => $conversation->contact->source,
                        'ctwa' => $this->ctwaForConversation($conversation),
                        'tags' => $conversation->contact->tags->map(fn ($tag) => [
                            'id' => $tag->id,
                            'name' => $tag->name,
                            'color' => $tag->color,
                        ])->values()],
                    'status' => $conversation->status,
                    'last_message_preview' => $conversation->last_message_preview,
                    'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                    'last_inbound_message_at' => $conversation->last_inbound_message_at
                        ? Carbon::parse($conversation->last_inbound_message_at)->toIso8601String()
                        : null,
                    'connection' => [
                        'id' => $conversation->connection->id,
                        'name' => $conversation->connection->name,
                        'slug' => $conversation->connection->slug,
                        'calling_status' => $conversation->connection->calling_status ?? 'unknown',
                        'calling_enabled' => (bool) ($conversation->connection->calling_enabled ?? false),
                        'calling_webhook_subscribed' => (bool) ($conversation->connection->calling_webhook_subscribed ?? false),
                    ],
                    'assigned_to' => Schema::hasColumn('whatsapp_conversations', 'assigned_to')
                        ? $conversation->assigned_to
                        : null,
                    'priority' => Schema::hasColumn('whatsapp_conversations', 'priority')
                        ? $conversation->priority
                        : null,
                    'automation_state' => $this->automationStateForConversation($conversation),
                    'automation_processing' => $this->isAutomationProcessing($conversation),
                    'automation_processing_mode' => ($conversation->metadata ?? [])['automation_processing_mode'] ?? null,
                    'bot_paused' => (bool) (($conversation->metadata ?? [])['bot_paused'] ?? false),
                    'bot_paused_reason' => ($conversation->metadata ?? [])['bot_paused_reason'] ?? null,
                    'handoff_status' => ($conversation->metadata ?? [])['handoff_status'] ?? null,
                    'handoff_reason' => ($conversation->metadata ?? [])['handoff_reason'] ?? null,
                    'message_counts' => $messageCounts[$conversation->id] ?? ['received' => 0, 'sent' => 0, 'total' => 0],
                ];
            })
            ->values();

        // Get new message notifications (conversations with new messages)
        $newMessageNotifications = WhatsAppConversation::where('account_id', $account->id)
            ->whereHas('messages', function ($query) use ($sinceDate) {
                $query->where('created_at', '>', $sinceDate)
                    ->where('direction', 'inbound');
            })
            ->with(['contact'])
            ->get()
            ->map(function ($conversation) {
                return [
                    'conversation_id' => $conversation->id,
                    'contact' => [
                        'id' => $conversation->contact?->id,
                        'name' => $conversation->contact?->name ?? $conversation->contact?->wa_id,
                        'wa_id' => $conversation->contact?->wa_id],
                    'last_message_preview' => $conversation->last_message_preview,
                    'last_activity_at' => $conversation->last_message_at?->toIso8601String()];
            })
            ->values();

        $updatedCalls = collect();
        if (Schema::hasTable('ai_voice_calls')) {
            $updatedCalls = WhatsAppCall::with('agent:id,name')
                ->where('account_id', $account->id)
                ->where('updated_at', '>', $sinceDate)
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (WhatsAppCall $call) => [
                    'id' => $call->id,
                    'whatsapp_connection_id' => $call->whatsapp_connection_id,
                    'direction' => $call->direction,
                    'phone_number' => $call->phone_number,
                    'contact_name' => $call->contact_name,
                    'status' => $call->status,
                    'route_mode' => $call->route_mode,
                    'routed_to' => $call->routed_to,
                    'provider_call_id' => $call->provider_call_id,
                    'duration_seconds' => $call->duration_seconds,
                    'summary' => $call->summary,
                    'metadata' => $call->metadata ?? [],
                    'agent' => $call->agent ? [
                        'id' => $call->agent->id,
                        'name' => $call->agent->name,
                    ] : null,
                    'created_at' => $call->created_at?->toIso8601String(),
                    'updated_at' => $call->updated_at?->toIso8601String(),
                ])
                ->values();
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'updated_conversations' => $updatedConversations,
            'new_message_notifications' => $newMessageNotifications,
            'updated_calls' => $updatedCalls]);
    }

    /**
     * Stream conversation updates (for polling fallback).
     */
    public function conversationStream(Request $request, WhatsAppConversation $conversation)
    {
        $account = $request->attributes->get('account') ?? current_account();

        // Ensure conversation belongs to account
        if (! account_ids_match($conversation->account_id, $account->id)) {
            abort(404);
        }

        $afterMessageId = (int) $request->query('after_message_id', 0);
        $afterNoteId = $request->query('after_note_id', 0);
        $afterAuditId = $request->query('after_audit_id', 0);
        $afterUpdatedAt = $request->query('after_updated_at');
        $afterUpdatedAtDate = $afterUpdatedAt ? Carbon::parse($afterUpdatedAt) : Carbon::now()->subMinutes(5);
        $messageBatchSize = ConversationThreadService::MESSAGE_BATCH_SIZE;
        $thread = app(ConversationThreadService::class);

        // When the client has no cursor yet (after_message_id=0), bootstrap with the latest bounded
        // slice instead of the full history, which can be too heavy on large threads.
        $newMessages = $thread->messagesAfter($conversation, $afterMessageId);

        $historyTruncated = false;
        if ($afterMessageId <= 0) {
            $historyTruncated = $thread->historyTruncated($conversation, $newMessages->count());
        }

        // Get updated messages (status changes) regardless of message id growth.
        // On the first load (no cursor) new_messages already carries the latest state, so skip it.
        $updatedMessages = $afterMessageId <= 0 ? collect() : WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->where('updated_at', '>', $afterUpdatedAtDate)
            ->where('id', '<=', $afterMessageId)
            ->where(function ($query) {
                $query->whereNotNull('sent_at')
                    ->orWhereNotNull('delivered_at')
                    ->orWhereNotNull('read_at');
            })
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn (WhatsAppMessage $message) => $thread->messagePayload($message))
            ->values();

        $newNotes = $thread->notesAfter($conversation, (int) $afterNoteId);
        $newAuditEvents = $thread->auditEventsAfter($conversation, (int) $afterAuditId);

        // Conversation meta if changed
        $conversationChanged = $conversation->updated_at > now()->subMinutes(5);
        $conversationMeta = null;
        if ($conversationChanged) {
            $conversationMeta = [
                'id' => $conversation->id,
                'status' => $conversation->status,
                'last_inbound_message_at' => $conversation->messages()
                    ->where('direction', 'inbound')
                    ->latest('created_at')
                    ->first()?->created_at?->toIso8601String(),
            ];

            if (Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
                $conversationMeta['assigned_to'] = $conversation->assigned_to;
            }

            if (Schema::hasColumn('whatsapp_conversations', 'priority')) {
                $conversationMeta['priority'] = $conversation->priority;
            }

            $conversationMeta['message_counts'] = $thread->messageCounts([$conversation->id])[$conversation->id];
            $conversationMeta['automation_state'] = $this->automationStateForConversation($conversation);
            $conversationMeta['automation_processing'] = $this->isAutomationProcessing($conversation);
            $conversationMeta['automation_processing_mode'] = ($conversation->metadata ?? [])['automation_processing_mode'] ?? null;
            $conversationMeta['bot_paused'] = (bool) (($conversation->metadata ?? [])['bot_paused'] ?? false);
            $conversationMeta['bot_paused_reason'] = ($conversation->metadata ?? [])['bot_paused_reason'] ?? null;
            $conversationMeta['handoff_status'] = ($conversation->metadata ?? [])['handoff_status'] ?? null;
            $conversationMeta['handoff_reason'] = ($conversation->metadata ?? [])['handoff_reason'] ?? null;
        }

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'new_messages' => $newMessages,
            'updated_messages' => $updatedMessages,
            'new_notes' => $newNotes,
            'new_audit_events' => $newAuditEvents,
            'conversation' => $conversationMeta,
            'history_truncated' => $historyTruncated,
            'batch_size' => $messageBatchSize]);
    }

    protected function automationStateForConversation(WhatsAppConversation $conversation): ?array
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $session = $metadata['automation_session'] ?? null;
        if (! is_array($session) || empty($session['flow_id']) || empty($session['waiting_node_id'])) {
            return null;
        }

        $flow = BotFlow::query()
            ->where('account_id', $conversation->account_id)
            ->where('id', (int) $session['flow_id'])
            ->first();
        $node = BotNode::query()
            ->where('account_id', $conversation->account_id)
            ->where('id', (int) $session['waiting_node_id'])
            ->first();

        $nodeConfig = is_array($node?->config) ? $node->config : [];

        return [
            'bot_id' => isset($session['bot_id']) ? (int) $session['bot_id'] : null,
            'flow_id' => (int) $session['flow_id'],
            'flow_name' => $flow?->name ?? 'Automation flow',
            'waiting_node_id' => (int) $session['waiting_node_id'],
            'waiting_node_label' => $nodeConfig['label']
                ?? $nodeConfig['title']
                ?? $nodeConfig['header_text']
                ?? $nodeConfig['body_text']
                ?? 'Waiting for reply',
            'status' => 'waiting',
            'invalid_replies' => (int) ($session['invalid_replies'] ?? 0),
            'updated_at' => $session['updated_at'] ?? null,
        ];
    }

    protected function isAutomationProcessing(WhatsAppConversation $conversation): bool
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        if (! ($metadata['automation_processing'] ?? false)) {
            return false;
        }

        $expiresAt = $metadata['automation_processing_expires_at'] ?? null;
        if (! $expiresAt) {
            return true;
        }

        try {
            return now()->lt(Carbon::parse($expiresAt));
        } catch (\Throwable) {
            return true;
        }
    }

    protected function ctwaForConversation(WhatsAppConversation $conversation): ?array
    {
        $conversationMeta = is_array($conversation->metadata) ? $conversation->metadata : [];
        $contactMeta = is_array($conversation->contact?->metadata) ? $conversation->contact->metadata : [];
        $ctwa = $conversationMeta['ctwa']['latest']
            ?? $conversationMeta['ctwa']
            ?? $contactMeta['ctwa']['latest']
            ?? $contactMeta['ctwa']
            ?? null;

        if (! is_array($ctwa) || $ctwa === []) {
            return null;
        }

        return [
            'source' => $ctwa['source'] ?? 'click_to_whatsapp_ad',
            'source_type' => $ctwa['source_type'] ?? null,
            'source_id' => $ctwa['source_id'] ?? null,
            'source_url' => $ctwa['source_url'] ?? null,
            'ctwa_clid' => $ctwa['ctwa_clid'] ?? null,
            'headline' => $ctwa['headline'] ?? null,
            'body' => $ctwa['body'] ?? null,
            'media_type' => $ctwa['media_type'] ?? null,
            'captured_at' => $ctwa['captured_at'] ?? null,
        ];
    }
}
