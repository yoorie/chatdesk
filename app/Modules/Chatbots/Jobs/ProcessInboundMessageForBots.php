<?php

namespace App\Modules\Chatbots\Jobs;

use App\Modules\Chatbots\Services\BotRuntime;
use App\Modules\WhatsApp\Events\Inbox\AuditEventAdded;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Services\AI\AiAgentAutopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessInboundMessageForBots implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public array $backoff = [5, 15, 60, 180];

    public int $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WhatsAppMessage $inboundMessage,
        public WhatsAppConversation $conversation
    ) {
        // Queue is selected by the dispatch site. Default queue should work
        // on hosts where only the default worker is running.
    }

    /**
     * Execute the job.
     */
    public function handle(BotRuntime $botRuntime, AiAgentAutopilotService $aiAgentAutopilot): void
    {
        $this->inboundMessage->refresh();
        $this->conversation->refresh();
        $account = $this->conversation->account;

        if (! $account || $this->inboundMessage->direction !== 'inbound') {
            $this->recordStatus('automation_skipped', 'No auto-reply sent', [
                'reason' => ! $account ? 'account_not_found' : 'not_inbound',
            ]);

            return;
        }

        Log::channel('chatbots')->debug('ProcessInboundMessageForBots started', [
            'account_id' => $this->conversation->account_id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->inboundMessage->id,
            'meta_message_id' => $this->inboundMessage->meta_message_id,
        ]);

        $chatbotsEnabled = module_enabled($account, 'automation.chatbots');
        $aiEnabled = module_enabled($account, 'ai');

        $this->setProcessingState($chatbotsEnabled ? 'automation' : 'ai');
        $this->recordStatus('automation_running', $chatbotsEnabled ? 'Checking for an auto-reply' : 'Writing an AI reply', [
            'inbound_message_id' => $this->inboundMessage->id,
            'chatbots_enabled' => $chatbotsEnabled,
            'ai_enabled' => $aiEnabled,
        ]);

        $chatbotMatched = false;

        if ($chatbotsEnabled) {
            $chatbotMatched = $botRuntime->processInboundMessage($this->inboundMessage, $this->conversation);
        }

        $aiResult = null;

        if (! $chatbotMatched && $aiEnabled) {
            try {
                $this->recordStatus('ai_replying', 'Writing an AI reply', [
                    'inbound_message_id' => $this->inboundMessage->id,
                ]);
                $aiResult = $aiAgentAutopilot->process($this->inboundMessage, $this->conversation);
            } catch (\Throwable $e) {
                $aiResult = [
                    'status' => 'failed',
                    'reason' => 'exception',
                    'error' => $e->getMessage(),
                ];
                $this->recordStatus('automation_failed', "Auto-reply couldn't be sent", [
                    'inbound_message_id' => $this->inboundMessage->id,
                    'error' => $e->getMessage(),
                ]);
                Log::channel('whatsapp')->warning('AI agent fallback processing failed', [
                    'account_id' => $account->id,
                    'conversation_id' => $this->conversation->id,
                    'message_id' => $this->inboundMessage->id,
                    'meta_message_id' => $this->inboundMessage->meta_message_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($chatbotMatched || (($aiResult['status'] ?? null) === 'sent')) {
            $this->recordStatus('automation_completed', $chatbotMatched ? 'Replied automatically' : 'AI replied', [
                'inbound_message_id' => $this->inboundMessage->id,
                'chatbot_matched' => $chatbotMatched,
                'ai_result' => $aiResult,
            ]);
        } elseif (($aiResult['status'] ?? null) === 'failed') {
            // Exceptions were already recorded above; don't add a second "couldn't be sent" line.
            if (($aiResult['reason'] ?? null) !== 'exception') {
                $this->recordStatus('automation_failed', "Auto-reply couldn't be sent", [
                    'inbound_message_id' => $this->inboundMessage->id,
                    'ai_result' => $aiResult,
                ]);
            }
        } elseif (($aiResult['status'] ?? null) === 'skipped') {
            $this->recordStatus('automation_skipped', $this->aiSkippedDescription((string) ($aiResult['reason'] ?? 'skipped')), [
                'inbound_message_id' => $this->inboundMessage->id,
                'ai_result' => $aiResult,
            ]);
        } else {
            $this->recordStatus('automation_skipped', 'No auto-reply sent', [
                'inbound_message_id' => $this->inboundMessage->id,
                'reason' => 'no_matching_bot_or_ai_disabled',
            ]);
        }

        Log::channel('chatbots')->debug('ProcessInboundMessageForBots finished', [
            'account_id' => $this->conversation->account_id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->inboundMessage->id,
        ]);

        $this->clearProcessingState();
    }

    public function uniqueId(): string
    {
        return 'chatbot-inbound-message:'.$this->inboundMessage->id;
    }

    public function failed(\Throwable $e): void
    {
        $this->clearProcessingState('failed');
        $this->recordStatus('automation_failed', "Auto-reply couldn't be sent", [
            'inbound_message_id' => $this->inboundMessage->id ?? null,
            'error' => $e->getMessage(),
        ]);

        Log::channel('chatbots')->error('ProcessInboundMessageForBots failed', [
            'account_id' => $this->conversation->account_id,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->inboundMessage->id,
            'meta_message_id' => $this->inboundMessage->meta_message_id,
            'error' => $e->getMessage(),
        ]);
    }

    protected function aiSkippedDescription(string $reason): string
    {
        return match ($reason) {
            'no_active_autopilot_agent' => 'No auto-reply sent: no AI assistant is switched on',
            'auto_reply_cap_reached' => 'No auto-reply sent: daily reply limit reached',
            'recent_human_reply' => 'No auto-reply sent: a team member replied recently',
            'unsupported_message_type' => 'No auto-reply sent: this kind of message needs a person',
            'conversation_closed' => 'No auto-reply sent: chat is closed',
            'ai_module_unavailable' => 'No auto-reply sent: AI replies are not on your plan',
            'escalation_keyword' => 'Handed to your team: customer asked for a person',
            'empty_suggestion' => 'No auto-reply sent: nothing useful to say',
            default => 'No auto-reply sent',
        };
    }

    protected function recordStatus(string $eventType, string $description, array $meta = []): void
    {
        try {
            $conversation = $this->conversation->fresh();
            if (! $conversation) {
                return;
            }

            $audit = WhatsAppConversationAuditEvent::create([
                'account_id' => $conversation->account_id,
                'whatsapp_conversation_id' => $conversation->id,
                'event_type' => $eventType,
                'description' => $description,
                'meta' => array_merge($meta, [
                    'source' => 'bot_runtime',
                    'status_kind' => 'automation',
                ]),
            ]);

            event(new AuditEventAdded($conversation, [
                'id' => $audit->id,
                'event_type' => $audit->event_type,
                'description' => $audit->description,
                'meta' => $audit->meta,
                'created_at' => $audit->created_at->toIso8601String(),
            ]));
        } catch (\Throwable $e) {
            Log::channel('chatbots')->debug('Failed to record automation status', [
                'conversation_id' => $this->conversation->id ?? null,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function setProcessingState(string $mode): void
    {
        try {
            $conversation = $this->conversation->fresh();
            if (! $conversation) {
                return;
            }

            $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
            $metadata['automation_processing'] = true;
            $metadata['automation_processing_mode'] = $mode;
            $metadata['automation_processing_started_at'] = now()->toIso8601String();
            $metadata['automation_processing_expires_at'] = now()->addMinutes(3)->toIso8601String();
            $metadata['automation_processing_message_id'] = $this->inboundMessage->id;

            $conversation->forceFill(['metadata' => $metadata])->save();
            event(new ConversationUpdated($conversation));
        } catch (\Throwable $e) {
            Log::channel('chatbots')->debug('Failed to set automation processing state', [
                'conversation_id' => $this->conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function clearProcessingState(?string $status = null): void
    {
        try {
            $conversation = $this->conversation->fresh();
            if (! $conversation) {
                return;
            }

            $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
            unset(
                $metadata['automation_processing'],
                $metadata['automation_processing_mode'],
                $metadata['automation_processing_started_at'],
                $metadata['automation_processing_expires_at'],
                $metadata['automation_processing_message_id']
            );
            if ($status) {
                $metadata['automation_last_status'] = $status;
                $metadata['automation_last_status_at'] = now()->toIso8601String();
            }

            $conversation->forceFill(['metadata' => $metadata])->save();
            event(new ConversationUpdated($conversation));
        } catch (\Throwable $e) {
            Log::channel('chatbots')->debug('Failed to clear automation processing state', [
                'conversation_id' => $this->conversation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
