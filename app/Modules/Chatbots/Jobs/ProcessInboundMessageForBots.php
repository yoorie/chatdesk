<?php

namespace App\Modules\Chatbots\Jobs;

use App\Modules\Chatbots\Services\BotRuntime;
use App\Modules\WhatsApp\Events\Inbox\AuditEventAdded;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
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
    public function handle(BotRuntime $botRuntime): void
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

        $this->setProcessingState('automation');
        $this->recordStatus('automation_running', 'Checking for an auto-reply', [
            'inbound_message_id' => $this->inboundMessage->id,
            'chatbots_enabled' => $chatbotsEnabled,
        ]);

        $chatbotMatched = $chatbotsEnabled
            && $botRuntime->processInboundMessage($this->inboundMessage, $this->conversation);

        if ($chatbotMatched) {
            $this->recordStatus('automation_completed', 'Replied automatically', [
                'inbound_message_id' => $this->inboundMessage->id,
                'chatbot_matched' => true,
            ]);
        } else {
            $this->recordStatus('automation_skipped', 'No auto-reply sent', [
                'inbound_message_id' => $this->inboundMessage->id,
                'reason' => 'no_matching_bot',
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
