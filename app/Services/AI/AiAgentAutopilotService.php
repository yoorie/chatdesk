<?php

namespace App\Services\AI;

use App\Core\Billing\EntitlementService;
use App\Core\Billing\PlanResolver;
use App\Core\Billing\UsageService;
use App\Models\AiAgent;
use App\Models\AiAgentRun;
use App\Models\AiUsageLog;
use App\Models\PlatformSetting;
use App\Modules\WhatsApp\Events\Inbox\AuditEventAdded;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Events\Inbox\MessageCreated;
use App\Modules\WhatsApp\Events\Inbox\MessageUpdated;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiAgentAutopilotService
{
    public function __construct(
        protected ConversationAssistantService $conversationAssistant,
        protected WhatsAppClient $whatsappClient,
        protected EntitlementService $entitlementService,
        protected UsageService $usageService,
        protected PlanResolver $planResolver
    ) {}

    public function process(WhatsAppMessage $inboundMessage, WhatsAppConversation $conversation): array
    {
        $conversation->loadMissing(['account', 'connection', 'contact']);
        $account = $conversation->account;

        if (! $account || (int) $inboundMessage->account_id !== (int) $account->id) {
            return ['status' => 'skipped', 'reason' => ! $account ? 'account_not_found' : 'account_mismatch'];
        }

        $agent = $this->activeAutopilotAgent($account->id);
        if (! $agent) {
            return ['status' => 'skipped', 'reason' => 'no_active_autopilot_agent'];
        }

        if ($this->shouldSkip($agent, $inboundMessage, $conversation, $reason)) {
            $this->recordRun($agent, $conversation, $inboundMessage, 'skipped', $reason);

            return ['status' => 'skipped', 'reason' => $reason, 'agent_id' => $agent->id];
        }

        try {
            $this->entitlementService->assertWithinLimit($account, 'messages_monthly', 1);

            $suggestion = trim($this->conversationAssistant->suggestReply(
                $conversation,
                25,
                $this->autopilotInstruction($agent),
                $agent,
                true,
                $this->maxTokensForAgent($agent)
            ));

            $suggestion = $this->normalizeSuggestion($suggestion);
            if ($suggestion === '') {
                $this->recordRun($agent, $conversation, $inboundMessage, 'skipped', 'empty_suggestion');

                return ['status' => 'skipped', 'reason' => 'empty_suggestion', 'agent_id' => $agent->id];
            }

            $outboundMessage = DB::transaction(function () use ($account, $conversation, $inboundMessage, $suggestion, $agent) {
                return WhatsAppMessage::create([
                    'account_id' => $account->id,
                    'whatsapp_conversation_id' => $conversation->id,
                    'direction' => 'outbound',
                    'type' => 'text',
                    'text_body' => $suggestion,
                    'payload' => [
                        'automation' => 'ai_agent_autopilot',
                        'ai_agent_id' => $agent->id,
                        'inbound_message_id' => $inboundMessage->id,
                    ],
                    'status' => 'queued',
                ]);
            });

            $outboundMessage->load('conversation.contact');
            event(new MessageCreated($outboundMessage));

            $response = $this->whatsappClient->sendTextMessage(
                $conversation->connection,
                $conversation->contact->wa_id,
                $suggestion,
                $inboundMessage->meta_message_id
            );

            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $outboundMessage->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($outboundMessage->payload ?? [], [
                    'response' => $response,
                ]),
            ]);

            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($suggestion, 100, ''),
                'metadata' => array_merge($conversation->metadata ?? [], [
                    'ai_agent_last_reply_at' => now()->toISOString(),
                    'ai_agent_last_agent_id' => $agent->id,
                ]),
            ]);

            if ($this->replyRequestsHumanHandoff($suggestion)) {
                $this->handoffToHuman($agent, $conversation, $inboundMessage, 'AI agent requested human takeover');
            }

            $this->usageService->incrementMessages($account, 1);
            $this->recordUsage($account->id, (int) $account->owner_id);
            $this->recordRun($agent, $conversation, $inboundMessage, 'sent', null, $suggestion, $outboundMessage);

            event(new MessageUpdated($outboundMessage));
            event(new ConversationUpdated($conversation->fresh('contact')));

            return [
                'status' => 'sent',
                'agent_id' => $agent->id,
                'message_id' => $outboundMessage->id,
                'meta_message_id' => $metaMessageId,
            ];
        } catch (\Throwable $e) {
            $status = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpException ? $e->getStatusCode() : 0;
            if ($status === 402) {
                $this->recordRun($agent, $conversation, $inboundMessage, 'skipped', 'credit_or_message_limit', null, null, $e->getMessage());

                return [
                    'status' => 'skipped',
                    'reason' => 'credit_or_message_limit',
                    'agent_id' => $agent->id,
                ];
            }

            $this->recordRun($agent, $conversation, $inboundMessage, 'failed', 'exception', null, null, $e->getMessage());

            Log::channel('whatsapp')->warning('AI agent autopilot failed', [
                'account_id' => $account->id,
                'conversation_id' => $conversation->id,
                'inbound_message_id' => $inboundMessage->id,
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'reason' => 'exception',
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function activeAutopilotAgent(int $accountId): ?AiAgent
    {
        if (! $this->platformAiEnabled() || ! Schema::hasTable('ai_agents')) {
            return null;
        }

        return AiAgent::where('account_id', $accountId)
            ->where('is_active', true)
            ->where('mode', 'autopilot')
            ->latest('updated_at')
            ->first();
    }

    protected function shouldSkip(AiAgent $agent, WhatsAppMessage $inboundMessage, WhatsAppConversation $conversation, ?string &$reason): bool
    {
        if ($inboundMessage->direction !== 'inbound') {
            $reason = 'not_inbound';

            return true;
        }

        if (! $this->messageHasReplyableText($inboundMessage)) {
            $reason = 'unsupported_message_type';

            return true;
        }

        if ($conversation->status === 'closed') {
            $reason = 'conversation_closed';

            return true;
        }

        $effectiveModules = $this->planResolver->getEffectiveModules($conversation->account);
        if (! in_array('ai', $effectiveModules, true)) {
            $reason = 'ai_module_unavailable';

            return true;
        }

        if ($this->containsEscalationKeyword($agent, (string) $inboundMessage->text_body)) {
            $reason = 'escalation_keyword';
            $this->handoffToHuman($agent, $conversation, $inboundMessage, 'Escalation keyword matched');

            return true;
        }

        $since = now()->subDay();
        $sentToday = WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', $since)
            ->where('payload->automation', 'ai_agent_autopilot')
            ->count();

        if ($sentToday >= (int) $agent->max_auto_replies_per_conversation) {
            $reason = 'auto_reply_cap_reached';

            return true;
        }

        $recentHumanReply = WhatsAppMessage::where('whatsapp_conversation_id', $conversation->id)
            ->where('direction', 'outbound')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->where(function ($query) {
                $query->whereNull('payload->automation');
            })
            ->exists();

        if ($recentHumanReply) {
            $reason = 'recent_human_reply';

            return true;
        }

        return false;
    }

    protected function messageHasReplyableText(WhatsAppMessage $message): bool
    {
        if (trim((string) $message->text_body) !== '') {
            return true;
        }

        $payload = is_array($message->payload) ? $message->payload : [];
        $interactive = is_array($payload['interactive'] ?? null) ? $payload['interactive'] : [];

        return trim((string) (
            $interactive['button_reply']['title']
            ?? $interactive['button_reply']['id']
            ?? $interactive['list_reply']['title']
            ?? $interactive['list_reply']['id']
            ?? ''
        )) !== '';
    }

    protected function containsEscalationKeyword(AiAgent $agent, string $text): bool
    {
        $keywords = array_filter($agent->escalation_rules['keywords'] ?? []);
        if (! $keywords) {
            return false;
        }

        $lower = Str::lower($text);

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && Str::contains($lower, Str::lower($keyword))) {
                return true;
            }
        }

        return false;
    }

    protected function autopilotInstruction(AiAgent $agent): string
    {
        return implode("\n", array_filter([
            'You are replying directly to a customer on WhatsApp.',
            'Send only the final customer-facing message.',
            'Do not mention that you are AI.',
            'Do not promise refunds, discounts, policies, delivery timelines, or legal outcomes unless explicitly present in the conversation.',
            'If the request requires human approval, ask one clarifying question or say the team will review it.',
            'Write a complete WhatsApp-friendly answer. Be concise, but do not cut off pricing, plan details, steps, or the final sentence.',
            'Use WhatsApp formatting only: *bold*, _italic_, ~strikethrough~, and simple hyphen bullets. Do not use Markdown syntax like **bold**, headings, tables, or fenced code blocks.',
            'For pricing questions, include the relevant plan names, prices, key differences, and the best next step when that information is available in your instructions or knowledge.',
            AiReplyGuardrail::paymentUrlInstruction(),
            'If exact pricing or policy details are not available, say that clearly and ask one useful qualifying question.',
            $agent->goal ? 'Primary goal: '.$agent->goal : null,
            ! empty($agent->allowed_actions) ? 'Allowed actions: '.implode(', ', $agent->allowed_actions).'.' : null,
            ! empty($agent->qualification_fields) ? 'Qualification fields to collect gradually: '.implode(', ', $agent->qualification_fields).'.' : null,
            ! empty($agent->handoff_rules['keywords'] ?? []) ? 'Handoff when these appear: '.implode(', ', $agent->handoff_rules['keywords']).'.' : null,
            $agent->fallback_reply ? 'Fallback when unsure: '.$agent->fallback_reply : null,
            $agent->max_reply_chars ? 'Maximum reply length: '.$agent->max_reply_chars.' characters.' : null,
        ]));
    }

    protected function maxTokensForAgent(AiAgent $agent): int
    {
        $maxChars = (int) ($agent->max_reply_chars ?: 2000);

        return max(500, min(3000, (int) ceil($maxChars / 3)));
    }

    protected function normalizeSuggestion(string $suggestion): string
    {
        $suggestion = trim(str_replace(["\r\n", "\r"], "\n", $suggestion));
        $suggestion = preg_replace('/[ \t]+/', ' ', $suggestion) ?? $suggestion;
        $suggestion = preg_replace("/\n{3,}/", "\n\n", $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/\*\*([^*\n][^*]*?)\*\*/', '*$1*', $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/__([^_\n][^_]*?)__/', '_$1_', $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/^#{1,6}\s+/m', '*', $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/^\s*[-*]\s+/m', '- ', $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/```(?:\w+)?\n?|\n?```/', '', $suggestion) ?? $suggestion;
        $suggestion = trim($suggestion);
        $suggestion = preg_replace('/\b\d{10,19}\b/', '[REDACTED-NUMBER]', $suggestion) ?? $suggestion;
        $suggestion = preg_replace('/\b(?:\d[ -]*?){13,16}\b/', '[REDACTED-CARD]', $suggestion) ?? $suggestion;

        return Str::limit($suggestion, 4000, '');
    }

    protected function replyRequestsHumanHandoff(string $reply): bool
    {
        $lower = Str::lower($reply);

        foreach ([
            'escalated this to our team',
            'escalated to our team',
            'specialist will join',
            'team will join this chat',
            'human agent will join',
            'handover to',
            'hand over to',
            'manual support',
        ] as $needle) {
            if (Str::contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function handoffToHuman(AiAgent $agent, WhatsAppConversation $conversation, WhatsAppMessage $inboundMessage, string $reason): void
    {
        try {
            $conversation->loadMissing(['account', 'contact', 'connection']);
            $account = $conversation->account;
            if (! $account) {
                return;
            }

            $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
            $metadata['bot_paused'] = true;
            $metadata['bot_paused_at'] = now()->toIso8601String();
            $metadata['bot_paused_reason'] = $reason;
            $metadata['handoff_status'] = 'manual';
            $metadata['handoff_reason'] = $reason;
            $metadata['handoff_source'] = 'ai_agent';
            $metadata['handoff_ai_agent_id'] = $agent->id;
            $metadata['handoff_at'] = now()->toIso8601String();
            unset(
                $metadata['automation_session'],
                $metadata['automation_processing'],
                $metadata['automation_processing_mode'],
                $metadata['automation_processing_started_at'],
                $metadata['automation_processing_expires_at'],
                $metadata['automation_processing_message_id']
            );

            $updates = ['metadata' => $metadata];
            if (! $conversation->assigned_to && $account->owner_id) {
                $updates['assigned_to'] = $account->owner_id;
            }

            $conversation->forceFill($updates)->save();

            $audit = WhatsAppConversationAuditEvent::create([
                'account_id' => $account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'event_type' => 'human_handoff_requested',
                'description' => 'AI handed this chat to a human agent',
                'meta' => [
                    'reason' => $reason,
                    'ai_agent_id' => $agent->id,
                    'inbound_message_id' => $inboundMessage->id,
                ],
            ]);

            event(new AuditEventAdded($conversation, [
                'id' => $audit->id,
                'event_type' => $audit->event_type,
                'description' => $audit->description,
                'meta' => $audit->meta,
                'created_at' => $audit->created_at->toIso8601String(),
            ]));

            app(AppNotificationService::class)->workspace(
                $account,
                'conversation_handoff',
                'Chat needs human attention',
                ($conversation->contact?->name ?: $conversation->contact?->wa_id ?: 'A customer').' was handed off by '.$agent->name.'.',
                'warning',
                route('app.whatsapp.conversations.index', ['conversation' => $conversation->id]),
                [
                    'conversation_id' => $conversation->id,
                    'ai_agent_id' => $agent->id,
                    'reason' => $reason,
                    'dedupe_key' => 'conversation_handoff_'.$conversation->id,
                ]
            );

            event(new ConversationUpdated($conversation->fresh(['contact', 'connection'])));
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Failed to mark AI handoff', [
                'conversation_id' => $conversation->id,
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function recordRun(
        AiAgent $agent,
        WhatsAppConversation $conversation,
        WhatsAppMessage $inboundMessage,
        string $status,
        ?string $reason = null,
        ?string $suggestion = null,
        ?WhatsAppMessage $outboundMessage = null,
        ?string $error = null
    ): void {
        if (! Schema::hasTable('ai_agent_runs')) {
            return;
        }

        AiAgentRun::create([
            'account_id' => $conversation->account_id,
            'ai_agent_id' => $agent->id,
            'whatsapp_conversation_id' => $conversation->id,
            'inbound_message_id' => $inboundMessage->id,
            'outbound_message_id' => $outboundMessage?->id,
            'status' => $status,
            'reason' => $reason,
            'suggestion' => $suggestion,
            'error_message' => $error ? Str::limit($error, 1000, '') : null,
            'metadata' => [
                'agent_mode' => $agent->mode,
                'message_type' => $inboundMessage->type,
            ],
        ]);
    }

    protected function recordUsage(int $accountId, int $ownerId): void
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return;
        }

        AiUsageLog::create([
            'user_id' => $ownerId,
            'account_id' => $accountId,
            'feature' => 'agent_autopilot',
        ]);
    }

    protected function platformAiEnabled(): bool
    {
        $value = PlatformSetting::get('ai.enabled', false);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
