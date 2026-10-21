<?php

namespace App\Modules\Chatbots\Services;

use App\Core\Billing\EntitlementService;
use App\Core\Billing\UsageService;
use App\Models\AccountAppointment;
use App\Models\AccountDeal;
use App\Models\AccountIntegration;
use App\Models\AiAgent;
use App\Modules\Chatbots\Models\BotEdge;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Events\Inbox\MessageCreated;
use App\Modules\WhatsApp\Events\Inbox\MessageUpdated;
use App\Modules\WhatsApp\Models\WhatsAppFlow;
use App\Modules\WhatsApp\Models\WhatsAppList;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\TemplateComposer;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use App\Services\AI\AiReplyGuardrail;
use App\Services\AI\ConversationAssistantService;
use App\Services\RazorpayPaymentLinkService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ActionExecutor
{
    protected const BOT_AUTOMATION_SOURCE = 'chatbot_flow';

    public function __construct(
        protected WhatsAppClient $whatsappClient,
        protected TemplateComposer $templateComposer,
        protected EntitlementService $entitlementService,
        protected UsageService $usageService
    ) {}

    /**
     * Execute an action node.
     */
    public function execute(BotNode $node, BotContext $context): array
    {
        $type = $node->type;
        $config = $node->config;
        $directActionTypes = ['send_text', 'send_template', 'send_buttons', 'send_list', 'send_media', 'send_flow', 'assign_agent', 'add_tag', 'add_segment', 'update_contact', 'create_deal', 'create_appointment', 'sync_integration', 'set_status', 'set_priority', 'handoff', 'send_payment_link', 'ai_agent_reply'];

        if (in_array($type, $directActionTypes, true)) {
            $config['action_type'] = $type;

            return $this->executeAction($config, $context);
        }

        return match ($type) {
            'action' => $this->executeAction($config, $context),
            'delay' => $this->scheduleDelay($node, $context),
            'webhook' => $this->callWebhook($config, $context),
            default => ['success' => false, 'error' => "Unknown action type: {$type}"],
        };
    }

    protected function executeAction(array $config, BotContext $context): array
    {
        $actionType = $config['action_type'] ?? null;

        return match ($actionType) {
            'send_text' => $this->sendTextMessage($config, $context),
            'send_template' => $this->sendTemplateMessage($config, $context),
            'send_buttons' => $this->sendButtonsMessage($config, $context),
            'send_list' => $this->sendListMessage($config, $context),
            'send_media' => $this->sendMediaMessage($config, $context),
            'send_flow' => $this->sendFlowMessage($config, $context),
            'assign_agent' => $this->assignAgent($config, $context),
            'add_tag' => $this->addTag($config, $context),
            'add_segment' => $this->addSegment($config, $context),
            'update_contact' => $this->updateContact($config, $context),
            'create_deal' => $this->createDeal($config, $context),
            'create_appointment' => $this->createAppointment($config, $context),
            'sync_integration' => $this->syncIntegration($config, $context),
            'set_status' => $this->setStatus($config, $context),
            'set_priority' => $this->setPriority($config, $context),
            'handoff' => $this->handoff($config, $context),
            'send_payment_link' => $this->sendPaymentLink($config, $context),
            'ai_agent_reply' => $this->sendAiAgentReply($config, $context),
            default => ['success' => false, 'error' => "Unknown action: {$actionType}"],
        };
    }

    protected function sendTextMessage(array $config, BotContext $context): array
    {
        try {
            // Check message limit
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);

            // Rate limit check (max 10 messages per conversation per minute)
            $rateLimitKey = "bot_rate_limit:{$context->conversation->id}";
            $messageCount = Cache::get($rateLimitKey, 0);
            if ($messageCount >= 10) {
                return ['success' => false, 'error' => 'Rate limit exceeded for this conversation'];
            }

            $messageText = trim((string) ($config['message'] ?? ''));
            if ($messageText === '') {
                return ['success' => false, 'error' => 'Message text is required'];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            // Send via WhatsApp API
            $response = $this->whatsappClient->sendTextMessage(
                $context->connection,
                $toWaId,
                $messageText
            );

            // Create message record
            $message = WhatsAppMessage::create([
                'account_id' => $context->account->id,
                'whatsapp_conversation_id' => $context->conversation->id,
                'direction' => 'outbound',
                'type' => 'text',
                'text_body' => $messageText,
                'status' => 'sent',
                'sent_at' => now(),
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'payload' => $this->automationPayload($context, 'send_text', [
                    'response' => $response,
                ])]);

            // Track usage
            $this->usageService->incrementMessages($context->account, 1);

            // Update conversation
            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($messageText, 0, 100)]);

            // Broadcast events
            event(new MessageCreated($message));
            event(new ConversationUpdated($context->conversation));

            // Update rate limit
            Cache::put($rateLimitKey, $messageCount + 1, 60);

            return [
                'success' => true,
                'message_id' => $message->id,
                'meta_message_id' => $message->meta_message_id];
        } catch (\Exception $e) {
            Log::channel('chatbots')->error('Failed to send text message', [
                'error' => $e->getMessage(),
                'context' => $context->account->id]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendTemplateMessage(array $config, BotContext $context): array
    {
        try {
            // Check limits
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);
            $this->entitlementService->assertWithinLimit($context->account, 'template_sends_monthly', 1);

            $templateId = $config['template_id'] ?? null;
            $variables = $config['variables'] ?? [];

            if (! $templateId) {
                return ['success' => false, 'error' => 'Template ID required'];
            }

            $template = \App\Modules\WhatsApp\Models\WhatsAppTemplate::find($templateId);
            if (! $template || ! account_ids_match($template->account_id, $context->account->id)) {
                return ['success' => false, 'error' => 'Template not found'];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            // Prepare payload
            $payload = $this->templateComposer->preparePayload($template, $toWaId, $variables);

            // Send via WhatsApp API
            $response = $this->whatsappClient->sendTemplateMessage(
                $context->connection,
                $toWaId,
                $template->name,
                $template->language,
                $payload['template']['components'] ?? []
            );

            // Create message record
            $message = WhatsAppMessage::create([
                'account_id' => $context->account->id,
                'whatsapp_conversation_id' => $context->conversation->id,
                'direction' => 'outbound',
                'type' => 'template',
                'text_body' => $this->templateComposer->renderPreview($template, $variables)['body'],
                'status' => 'sent',
                'sent_at' => now(),
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'payload' => $this->automationPayload($context, 'send_template', $payload + [
                    'response' => $response,
                ])]);

            // Track usage
            $this->usageService->incrementMessages($context->account, 1);
            $this->usageService->incrementTemplateSends($context->account, 1);

            // Update conversation
            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($message->text_body, 0, 100)]);

            // Broadcast events
            event(new MessageCreated($message));
            event(new ConversationUpdated($context->conversation));

            return [
                'success' => true,
                'message_id' => $message->id,
                'meta_message_id' => $message->meta_message_id];
        } catch (\Exception $e) {
            Log::channel('chatbots')->error('Failed to send template message', [
                'error' => $e->getMessage(),
                'context' => $context->account->id]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendButtonsMessage(array $config, BotContext $context): array
    {
        try {
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);

            $bodyText = trim((string) ($config['body_text'] ?? $config['message'] ?? ''));
            $buttons = $config['buttons'] ?? [];
            $headerText = isset($config['header_text']) ? trim((string) $config['header_text']) : null;
            $footerText = isset($config['footer_text']) ? trim((string) $config['footer_text']) : null;

            if ($bodyText === '') {
                return ['success' => false, 'error' => 'Body text is required for send_buttons'];
            }

            if (! is_array($buttons) || count($buttons) < 1 || count($buttons) > 3) {
                return ['success' => false, 'error' => 'Buttons must be an array with 1 to 3 items'];
            }

            $normalizedButtons = [];
            foreach ($buttons as $index => $button) {
                if (! is_array($button)) {
                    return ['success' => false, 'error' => "Invalid button at index {$index}"];
                }

                $text = trim((string) ($button['text'] ?? ''));
                if ($text === '') {
                    return ['success' => false, 'error' => "Button text is required at index {$index}"];
                }

                $normalizedButtons[] = [
                    'id' => (string) ($button['id'] ?? ('btn_'.($index + 1))),
                    'text' => $text,
                ];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            $response = $this->whatsappClient->sendInteractiveButtons(
                $context->connection,
                $toWaId,
                $bodyText,
                $normalizedButtons,
                $headerText ?: null,
                $footerText ?: null
            );

            $message = WhatsAppMessage::create([
                'account_id' => $context->account->id,
                'whatsapp_conversation_id' => $context->conversation->id,
                'direction' => 'outbound',
                'type' => 'interactive',
                'text_body' => $bodyText,
                'status' => 'sent',
                'sent_at' => now(),
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'payload' => [
                    'automation' => self::BOT_AUTOMATION_SOURCE,
                    'bot_execution_id' => $context->metadata['execution_id'] ?? null,
                    'action_type' => 'send_buttons',
                    'interactive_type' => 'button',
                    'buttons' => $normalizedButtons,
                    'header_text' => $headerText,
                    'footer_text' => $footerText,
                    'response' => $response,
                ],
            ]);

            $this->usageService->incrementMessages($context->account, 1);

            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr($bodyText, 0, 100),
            ]);

            event(new MessageCreated($message));
            event(new ConversationUpdated($context->conversation));

            return [
                'success' => true,
                'message_id' => $message->id,
                'meta_message_id' => $message->meta_message_id,
            ];
        } catch (\Exception $e) {
            Log::channel('chatbots')->error('Failed to send interactive buttons', [
                'error' => $e->getMessage(),
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendListMessage(array $config, BotContext $context): array
    {
        try {
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);

            $listId = $config['list_id'] ?? null;
            if (! is_numeric($listId) || (int) $listId <= 0) {
                return ['success' => false, 'error' => 'Valid list_id is required for send_list'];
            }

            $list = WhatsAppList::where('account_id', $context->account->id)
                ->where('whatsapp_connection_id', $context->connection->id)
                ->where('is_active', true)
                ->find((int) $listId);

            if (! $list) {
                return ['success' => false, 'error' => 'List not found or inactive for this connection'];
            }

            $listFormat = $list->toMetaFormat();
            $sections = $listFormat['action']['sections'] ?? [];
            if (! is_array($sections) || empty($sections)) {
                return ['success' => false, 'error' => 'List has no sections to send'];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            $response = $this->whatsappClient->sendListMessage(
                $context->connection,
                $toWaId,
                (string) ($list->button_text ?: 'Choose'),
                $sections,
                $listFormat['header']['text'] ?? null,
                $listFormat['body']['text'] ?? ($list->description ?: $list->name),
                $listFormat['footer']['text'] ?? null
            );

            $message = WhatsAppMessage::create([
                'account_id' => $context->account->id,
                'whatsapp_conversation_id' => $context->conversation->id,
                'direction' => 'outbound',
                'type' => 'interactive',
                'text_body' => $list->description ?: $list->name,
                'status' => 'sent',
                'sent_at' => now(),
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'payload' => [
                    'automation' => self::BOT_AUTOMATION_SOURCE,
                    'bot_execution_id' => $context->metadata['execution_id'] ?? null,
                    'action_type' => 'send_list',
                    'interactive_type' => 'list',
                    'list_id' => $list->id,
                    'list_name' => $list->name,
                    'interactive' => $listFormat,
                    'response' => $response,
                ],
            ]);

            $this->usageService->incrementMessages($context->account, 1);

            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => substr((string) $list->name, 0, 100),
            ]);

            event(new MessageCreated($message));
            event(new ConversationUpdated($context->conversation));

            return [
                'success' => true,
                'message_id' => $message->id,
                'meta_message_id' => $message->meta_message_id,
            ];
        } catch (\Exception $e) {
            Log::channel('chatbots')->error('Failed to send interactive list', [
                'error' => $e->getMessage(),
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendMediaMessage(array $config, BotContext $context): array
    {
        try {
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);

            $mediaType = strtolower((string) ($config['media_type'] ?? 'image'));
            if (! in_array($mediaType, ['image', 'video', 'document', 'audio'], true)) {
                return ['success' => false, 'error' => 'Unsupported media type'];
            }

            $mediaUrl = trim((string) ($config['media_url'] ?? $config['url'] ?? ''));
            if ($mediaUrl === '' || ! filter_var($mediaUrl, FILTER_VALIDATE_URL)) {
                return ['success' => false, 'error' => 'A public media URL is required'];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            $caption = isset($config['caption']) ? trim((string) $config['caption']) : null;
            $filename = isset($config['filename']) ? trim((string) $config['filename']) : null;

            $response = $this->whatsappClient->sendMediaMessage(
                $context->connection,
                $toWaId,
                $mediaType,
                $mediaUrl,
                $caption ?: null,
                $filename ?: null
            );

            $message = WhatsAppMessage::create([
                'account_id' => $context->account->id,
                'whatsapp_conversation_id' => $context->conversation->id,
                'direction' => 'outbound',
                'type' => $mediaType,
                'text_body' => $caption ?: ucfirst($mediaType).' message',
                'status' => 'sent',
                'sent_at' => now(),
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'payload' => [
                    'automation' => self::BOT_AUTOMATION_SOURCE,
                    'bot_execution_id' => $context->metadata['execution_id'] ?? null,
                    'action_type' => 'send_media',
                    'media' => [
                        'link' => $mediaUrl,
                        'caption' => $caption,
                        'filename' => $filename,
                    ],
                    'response' => $response,
                ],
            ]);

            $this->usageService->incrementMessages($context->account, 1);
            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($message->text_body ?: ucfirst($mediaType).' message', 100, ''),
            ]);

            event(new MessageCreated($message));
            event(new ConversationUpdated($context->conversation));

            return ['success' => true, 'message_id' => $message->id, 'meta_message_id' => $message->meta_message_id];
        } catch (\Throwable $e) {
            Log::channel('chatbots')->error('Failed to send media message', [
                'error' => $e->getMessage(),
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendFlowMessage(array $config, BotContext $context): array
    {
        try {
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);

            $flowId = $config['flow_id'] ?? null;
            $metaFlowId = trim((string) ($config['meta_flow_id'] ?? ''));
            $flow = null;

            if (is_numeric($flowId) && (int) $flowId > 0) {
                $flow = WhatsAppFlow::where('account_id', $context->account->id)->find((int) $flowId);
                $metaFlowId = (string) ($flow?->meta_flow_id ?: $metaFlowId);
            }

            if ($metaFlowId === '') {
                return ['success' => false, 'error' => 'WhatsApp Flow ID is required'];
            }

            if ($flow && $flow->whatsapp_connection_id && (int) $flow->whatsapp_connection_id !== (int) $context->connection->id) {
                return ['success' => false, 'error' => 'Selected flow belongs to a different WhatsApp connection'];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            $bodyText = trim((string) ($config['body_text'] ?? $config['message'] ?? 'Please complete this form.'));
            $cta = trim((string) ($config['cta'] ?? 'Open form'));
            $headerText = trim((string) ($config['header_text'] ?? ''));
            $footerText = trim((string) ($config['footer_text'] ?? ''));
            $flowToken = trim((string) ($config['flow_token'] ?? ''));
            $flowAction = trim((string) ($config['flow_action'] ?? 'navigate')) ?: 'navigate';
            $screen = trim((string) ($config['screen'] ?? ''));

            if ($bodyText === '' || $cta === '') {
                return ['success' => false, 'error' => 'Flow body and CTA are required'];
            }

            $response = $this->whatsappClient->sendFlowMessage(
                $context->connection,
                $toWaId,
                $metaFlowId,
                $bodyText,
                $cta,
                $headerText ?: null,
                $footerText ?: null,
                $flowToken,
                $flowAction,
                $screen ?: null
            );

            $message = WhatsAppMessage::create([
                'account_id' => $context->account->id,
                'whatsapp_conversation_id' => $context->conversation->id,
                'direction' => 'outbound',
                'type' => 'interactive',
                'text_body' => $bodyText,
                'status' => 'sent',
                'sent_at' => now(),
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'payload' => [
                    'automation' => self::BOT_AUTOMATION_SOURCE,
                    'bot_execution_id' => $context->metadata['execution_id'] ?? null,
                    'action_type' => 'send_flow',
                    'interactive_type' => 'flow',
                    'flow_id' => $flow?->id,
                    'meta_flow_id' => $metaFlowId,
                    'flow_name' => $flow?->name,
                    'cta' => $cta,
                    'response' => $response,
                ],
            ]);

            $this->usageService->incrementMessages($context->account, 1);
            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($bodyText, 100, ''),
            ]);

            event(new MessageCreated($message));
            event(new ConversationUpdated($context->conversation));

            return ['success' => true, 'message_id' => $message->id, 'meta_message_id' => $message->meta_message_id];
        } catch (\Throwable $e) {
            Log::channel('chatbots')->error('Failed to send WhatsApp Flow message', [
                'error' => $e->getMessage(),
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function assignAgent(array $config, BotContext $context): array
    {
        $agentId = $config['agent_id'] ?? null;

        if (! $agentId) {
            return ['success' => false, 'error' => 'Agent ID is required'];
        }

        if (! Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            Log::channel('chatbots')->info('Assign agent action skipped (assigned_to not available)', [
                'conversation_id' => $context->conversation->id,
                'agent_id' => $agentId]);

            return ['success' => true, 'note' => 'Agent assignment not available'];
        }

        $assignableIds = $context->account->getAssignableAgentIds();
        if (! in_array((int) $agentId, $assignableIds, true)) {
            Log::channel('chatbots')->warning('Assign agent skipped: agent not in account team', [
                'conversation_id' => $context->conversation->id,
                'agent_id' => $agentId,
                'account_id' => $context->account->id]);

            return ['success' => false, 'error' => 'Selected agent is not a team member for this account'];
        }

        $context->conversation->update(['assigned_to' => $agentId]);
        event(new ConversationUpdated($context->conversation));

        return ['success' => true];
    }

    protected function updateContact(array $config, BotContext $context): array
    {
        $contact = $context->conversation->contact;
        if (! $contact) {
            return ['success' => false, 'error' => 'No contact available to update'];
        }

        $updates = [];
        foreach (['name', 'email', 'phone', 'company', 'status', 'notes', 'source'] as $field) {
            if (array_key_exists($field, $config) && trim((string) $config[$field]) !== '') {
                $updates[$field] = trim((string) $config[$field]);
            }
        }

        if (isset($config['custom_fields']) && is_array($config['custom_fields'])) {
            $updates['custom_fields'] = array_merge($contact->custom_fields ?: [], $config['custom_fields']);
        }

        if (isset($config['metadata']) && is_array($config['metadata'])) {
            $updates['metadata'] = array_merge($contact->metadata ?: [], $config['metadata']);
        }

        if ($updates === []) {
            return ['success' => false, 'error' => 'No contact fields were provided'];
        }

        $contact->update($updates);

        return ['success' => true, 'contact_id' => $contact->id, 'updated' => array_keys($updates)];
    }

    protected function createDeal(array $config, BotContext $context): array
    {
        $contact = $context->conversation->contact;
        $title = trim((string) ($config['title'] ?? 'WhatsApp lead'));
        if ($title === '') {
            return ['success' => false, 'error' => 'Deal title is required'];
        }

        $ownerId = $config['owner_id'] ?? $context->conversation->assigned_to;
        if ($ownerId && ! in_array((int) $ownerId, $context->account->getAssignableAgentIds(), true)) {
            return ['success' => false, 'error' => 'Deal owner is not an account team member'];
        }

        $deal = AccountDeal::create([
            'account_id' => $context->account->id,
            'whatsapp_contact_id' => $contact?->id,
            'whatsapp_conversation_id' => $context->conversation->id,
            'owner_id' => $ownerId ? (int) $ownerId : null,
            'title' => $title,
            'stage' => $config['stage'] ?? 'new',
            'value' => max(0, (int) ($config['value'] ?? 0)),
            'currency' => strtoupper((string) ($config['currency'] ?? 'INR')),
            'source' => $config['source'] ?? 'automation',
            'next_follow_up_at' => ! empty($config['next_follow_up_at']) ? \Illuminate\Support\Carbon::parse($config['next_follow_up_at']) : null,
            'metadata' => [
                'bot_execution_id' => $context->metadata['execution_id'] ?? null,
                'node_source' => 'chatbot_action',
            ],
        ]);

        return ['success' => true, 'deal_id' => $deal->id];
    }

    protected function addTag(array $config, BotContext $context): array
    {
        $contact = $context->conversation->contact;
        if (! $contact) {
            return ['success' => false, 'error' => 'No contact available for tagging'];
        }

        $tagId = $config['tag_id'] ?? null;
        $tagName = $config['tag'] ?? $config['tag_name'] ?? null;

        if (! $tagId && ! $tagName) {
            return ['success' => false, 'error' => 'Tag ID or name is required'];
        }

        $tag = null;
        if ($tagId) {
            $tag = ContactTag::where('account_id', $context->account->id)
                ->where('id', $tagId)
                ->first();
        } else {
            $tag = ContactTag::firstOrCreate(
                [
                    'account_id' => $context->account->id,
                    'name' => $tagName,
                ],
                [
                    'color' => $config['color'] ?? '#64748b',
                ]
            );
        }

        if (! $tag) {
            return ['success' => false, 'error' => 'Tag not found'];
        }

        $contact->tags()->syncWithoutDetaching([$tag->id]);

        return ['success' => true];
    }

    protected function addSegment(array $config, BotContext $context): array
    {
        $contact = $context->conversation->contact;
        if (! $contact) {
            return ['success' => false, 'error' => 'No contact available for segmenting'];
        }

        $segmentId = $config['segment_id'] ?? null;
        $segmentName = trim((string) ($config['segment_name'] ?? $config['segment'] ?? ''));

        if ((! is_numeric($segmentId) || (int) $segmentId <= 0) && $segmentName === '') {
            return ['success' => false, 'error' => 'Segment ID or name is required'];
        }

        $segment = null;
        if (is_numeric($segmentId) && (int) $segmentId > 0) {
            $segment = ContactSegment::where('account_id', $context->account->id)->find((int) $segmentId);
        } else {
            $segment = ContactSegment::firstOrCreate(
                [
                    'account_id' => $context->account->id,
                    'name' => $segmentName,
                ],
                [
                    'created_by' => $context->metadata['user_id'] ?? null,
                    'description' => 'Created by automation',
                    'filters' => [],
                    'contact_count' => 0,
                ]
            );
        }

        if (! $segment) {
            return ['success' => false, 'error' => 'Segment not found'];
        }

        $segment->contacts()->syncWithoutDetaching([$contact->id]);
        $segment->forceFill([
            'contact_count' => $segment->contacts()->count(),
            'last_calculated_at' => now(),
        ])->save();

        return ['success' => true, 'segment_id' => $segment->id, 'contact_id' => $contact->id];
    }

    protected function setStatus(array $config, BotContext $context): array
    {
        $status = $config['status'] ?? 'open';
        $context->conversation->update(['status' => $status]);

        event(new ConversationUpdated($context->conversation));

        return ['success' => true];
    }

    protected function setPriority(array $config, BotContext $context): array
    {
        $priority = $config['priority'] ?? null;
        if (! $priority) {
            return ['success' => false, 'error' => 'Priority is required'];
        }

        if (! Schema::hasColumn('whatsapp_conversations', 'priority')) {
            Log::channel('chatbots')->info('Set priority action skipped (priority not available)', [
                'conversation_id' => $context->conversation->id,
                'priority' => $priority]);

            return ['success' => true, 'note' => 'Priority not available'];
        }

        $context->conversation->update(['priority' => $priority]);
        event(new ConversationUpdated($context->conversation));

        return ['success' => true];
    }

    protected function handoff(array $config, BotContext $context): array
    {
        $updates = [
            'status' => $config['status'] ?? 'open',
            'priority' => $config['priority'] ?? 'high',
        ];

        if (! Schema::hasColumn('whatsapp_conversations', 'priority')) {
            unset($updates['priority']);
        }

        $agentId = $config['agent_id'] ?? null;
        if ($agentId) {
            $assignableIds = $context->account->getAssignableAgentIds();
            if (! in_array((int) $agentId, $assignableIds, true)) {
                return ['success' => false, 'error' => 'Selected handoff agent is not a team member'];
            }
            $updates['assigned_to'] = (int) $agentId;
        }

        $metadata = $context->conversation->metadata ?: [];
        $metadata['handoff'] = [
            'requested_at' => now()->toIso8601String(),
            'reason' => $config['reason'] ?? 'automation_handoff',
            'bot_execution_id' => $context->metadata['execution_id'] ?? null,
        ];
        $updates['metadata'] = $metadata;

        $context->conversation->update($updates);
        event(new ConversationUpdated($context->conversation));

        return ['success' => true];
    }

    protected function createAppointment(array $config, BotContext $context): array
    {
        $contact = $context->conversation->contact;
        if (! $contact) {
            return ['success' => false, 'error' => 'No contact available for appointment'];
        }

        $title = trim((string) ($config['title'] ?? 'WhatsApp appointment'));
        $scheduledAt = trim((string) ($config['scheduled_at'] ?? ''));
        $minutesFromNow = (int) ($config['minutes_from_now'] ?? 0);

        try {
            $date = $scheduledAt !== ''
                ? \Illuminate\Support\Carbon::parse($scheduledAt)
                : now()->addMinutes(max(15, $minutesFromNow ?: 60));
        } catch (\Throwable) {
            return ['success' => false, 'error' => 'Invalid appointment date'];
        }

        $appointment = AccountAppointment::create([
            'account_id' => $context->account->id,
            'external_source' => 'chatbot_flow',
            'external_id' => 'conversation_'.$context->conversation->id.'_'.($context->metadata['execution_id'] ?? time()),
            'title' => $title,
            'contact_name' => $contact->name ?: $contact->wa_id,
            'contact_phone' => $contact->phone ?: $contact->wa_id,
            'scheduled_at' => $date,
            'duration_minutes' => max(5, min(480, (int) ($config['duration_minutes'] ?? 30))),
            'staff_name' => trim((string) ($config['staff_name'] ?? '')) ?: null,
            'status' => $config['status'] ?? 'scheduled',
            'type' => trim((string) ($config['type'] ?? 'WhatsApp')) ?: 'WhatsApp',
            'location' => trim((string) ($config['location'] ?? '')) ?: null,
            'meeting_url' => trim((string) ($config['meeting_url'] ?? '')) ?: null,
            'description' => trim((string) ($config['description'] ?? 'Created by chatbot automation')) ?: null,
            'reminder_enabled' => (bool) ($config['reminder_enabled'] ?? true),
            'reminder_minutes_before' => max(0, min(10080, (int) ($config['reminder_minutes_before'] ?? 60))),
            'metadata' => [
                'conversation_id' => $context->conversation->id,
                'bot_execution_id' => $context->metadata['execution_id'] ?? null,
            ],
        ]);

        return ['success' => true, 'appointment_id' => $appointment->id, 'scheduled_at' => $appointment->scheduled_at?->toIso8601String()];
    }

    protected function syncIntegration(array $config, BotContext $context): array
    {
        $provider = trim((string) ($config['provider'] ?? ''));
        if ($provider === '') {
            return ['success' => false, 'error' => 'Integration provider is required'];
        }

        $integration = AccountIntegration::where('account_id', $context->account->id)
            ->where('provider', $provider)
            ->where('status', 'connected')
            ->first();

        if (! $integration) {
            return ['success' => false, 'error' => "Connected {$provider} integration not found"];
        }

        try {
            throw new \RuntimeException('Integration sync is not available in the ChatDesk open-source edition.');

            return [
                'success' => true,
                'provider' => $provider,
                'created' => (int) ($result['created'] ?? 0),
                'updated' => (int) ($result['updated'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::channel('chatbots')->warning('Automation integration sync failed', [
                'account_id' => $context->account->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendPaymentLink(array $config, BotContext $context): array
    {
        $paymentUrl = trim((string) ($config['payment_url'] ?? ''));
        $amount = max(0, (int) ($config['amount'] ?? 0));
        $currency = strtoupper((string) ($config['currency'] ?? 'INR'));

        $shouldCreateRazorpayLink = (bool) ($config['create_razorpay_link'] ?? false);

        if ($shouldCreateRazorpayLink || $paymentUrl === '') {
            try {
                $contact = $context->conversation->contact;
                $link = app(RazorpayPaymentLinkService::class)->createForAccount($context->account, [
                    'amount' => $amount,
                    'currency' => $currency,
                    'reference_id' => 'bot_'.$context->conversation->id.'_'.time(),
                    'description' => $config['description'] ?? 'WhatsApp payment request',
                    'customer_name' => $contact?->name ?: 'Customer',
                    'customer_phone' => $contact?->phone ?: $contact?->wa_id,
                    'customer_email' => $contact?->email,
                    'notes' => [
                        'source' => 'chatbot',
                        'conversation_id' => (string) $context->conversation->id,
                        'bot_execution_id' => (string) ($context->metadata['execution_id'] ?? ''),
                    ],
                ]);
                $paymentUrl = (string) ($link['short_url'] ?? $link['url'] ?? '');

                $metadata = $context->conversation->metadata ?: [];
                $metadata['last_razorpay_payment_link'] = [
                    'id' => $link['id'] ?? null,
                    'short_url' => $paymentUrl,
                    'amount' => $amount,
                    'created_at' => now()->toIso8601String(),
                ];
                $context->conversation->update(['metadata' => $metadata]);
            } catch (\Throwable $e) {
                if ($shouldCreateRazorpayLink && ! ($config['allow_static_fallback'] ?? false)) {
                    return ['success' => false, 'error' => 'Dynamic Razorpay payment link creation failed: '.$e->getMessage()];
                }

                $fallbackUrl = trim((string) ($config['fallback_payment_url'] ?? ''));
                if ($fallbackUrl !== '' && filter_var($fallbackUrl, FILTER_VALIDATE_URL)) {
                    $paymentUrl = $fallbackUrl;
                } else {
                    return ['success' => false, 'error' => 'Payment link creation failed: '.$e->getMessage()];
                }
            }
        }

        if ($paymentUrl === '' || ! filter_var($paymentUrl, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'A valid payment URL is required'];
        }

        if (AiReplyGuardrail::isInvalidPlatformPaymentUrl($paymentUrl)) {
            return ['success' => false, 'error' => ('Invalid '.brand_name().' checkout URL. Use dynamic Razorpay payment links or send '.url('/pricing').'.')];
        }

        $message = trim((string) ($config['message'] ?? 'Please complete your payment using this link: {{payment_url}}'));
        $message = str_replace(
            ['{{payment_url}}', '{{amount}}', '{{currency}}'],
            [$paymentUrl, $amount > 0 ? number_format($amount / 100, 2) : '', $currency],
            $message
        );

        return $this->sendTextMessage([
            'message' => $message,
        ], $context);
    }

    protected function sendAiAgentReply(array $config, BotContext $context): array
    {
        if (! Schema::hasTable('ai_agents')) {
            return ['success' => false, 'error' => 'AI agents are not available'];
        }

        $agent = null;
        $agentId = $config['agent_id'] ?? null;
        if ($agentId) {
            $agent = AiAgent::where('account_id', $context->account->id)
                ->where('is_active', true)
                ->find((int) $agentId);
        }

        if (! $agent) {
            $role = $config['agent_role'] ?? null;
            $agent = AiAgent::where('account_id', $context->account->id)
                ->where('is_active', true)
                ->when($role, fn ($query) => $query->where('role', $role))
                ->latest()
                ->first();
        }

        if (! $agent) {
            return ['success' => false, 'error' => 'No active AI agent found'];
        }

        try {
            $this->entitlementService->assertWithinLimit($context->account, 'messages_monthly', 1);

            $instruction = trim((string) ($config['instruction'] ?? 'Reply as this AI agent. Help the customer, qualify intent, and suggest the next step. Be concise but complete.'));
            $maxChars = max(120, min(3900, (int) ($config['max_chars'] ?? $agent->max_reply_chars ?? 3500)));
            $instruction .= "\n\nWhatsApp automation limit: respond in a concise WhatsApp-safe format, max {$maxChars} characters. Include complete pricing or plan details when the customer asks for them. Do not include markdown tables, very long sections, or unfinished sentences. ".AiReplyGuardrail::paymentUrlInstruction();
            $suggestion = trim(app(ConversationAssistantService::class)->suggestReply(
                $context->conversation,
                25,
                $instruction,
                $agent,
                true,
                max(500, min(3000, (int) ceil($maxChars / 3)))
            ));

            $suggestion = trim(preg_replace('/^["\']|["\']$/', '', $suggestion) ?? $suggestion);
            $suggestion = AiReplyGuardrail::sanitize($suggestion);
            $suggestion = $this->limitWhatsAppText($suggestion, $maxChars);
            if ($suggestion === '') {
                return ['success' => false, 'error' => 'AI agent returned an empty reply'];
            }

            $contact = $context->conversation->contact;
            $toWaId = $contact?->wa_id;
            if (! $toWaId) {
                return ['success' => false, 'error' => 'Conversation contact wa_id not found'];
            }

            $outboundMessage = DB::transaction(function () use ($context, $suggestion, $agent) {
                return WhatsAppMessage::create([
                    'account_id' => $context->account->id,
                    'whatsapp_conversation_id' => $context->conversation->id,
                    'direction' => 'outbound',
                    'type' => 'text',
                    'text_body' => $suggestion,
                    'status' => 'queued',
                    'payload' => [
                        'automation' => 'chatbot_ai_agent_node',
                        'ai_agent_id' => $agent->id,
                        'bot_execution_id' => $context->metadata['execution_id'] ?? null,
                    ],
                ]);
            });

            event(new MessageCreated($outboundMessage));

            $response = $this->whatsappClient->sendTextMessage(
                $context->connection,
                $toWaId,
                $suggestion,
                $context->inboundMessage->meta_message_id
            );

            $outboundMessage->update([
                'meta_message_id' => $response['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($outboundMessage->payload ?? [], ['response' => $response]),
            ]);

            $this->usageService->incrementMessages($context->account, 1);
            $context->conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($suggestion, 100, ''),
                'metadata' => array_merge($context->conversation->metadata ?? [], [
                    'ai_agent_last_reply_at' => now()->toISOString(),
                    'ai_agent_last_agent_id' => $agent->id,
                    'ai_agent_transfer_source' => 'chatbot_flow',
                ]),
            ]);

            event(new MessageUpdated($outboundMessage));
            event(new ConversationUpdated($context->conversation));

            return ['success' => true, 'message_id' => $outboundMessage->id, 'ai_agent_id' => $agent->id];
        } catch (\Throwable $e) {
            Log::channel('chatbots')->error('Failed to run AI agent node', [
                'error' => $e->getMessage(),
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
                'agent_id' => $agent->id,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function limitWhatsAppText(string $text, int $maxChars = 1500): string
    {
        $maxChars = max(120, min($maxChars, 3000));
        $normalized = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $text)) ?? $text);

        if (mb_strlen($normalized) <= $maxChars) {
            return $normalized;
        }

        $truncated = mb_substr($normalized, 0, $maxChars);
        $lastSentence = max(
            mb_strrpos($truncated, '.') ?: 0,
            mb_strrpos($truncated, '?') ?: 0,
            mb_strrpos($truncated, '!') ?: 0
        );

        if ($lastSentence > 120) {
            return trim(mb_substr($truncated, 0, $lastSentence + 1));
        }

        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace && $lastSpace > 120) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return rtrim($truncated, " \t\n\r\0\x0B.,;:").'...';
    }

    protected function scheduleDelay(BotNode $node, BotContext $context): array
    {
        $delaySeconds = $node->config['seconds'] ?? 0;
        if ($delaySeconds <= 0) {
            return ['success' => false, 'error' => 'Invalid delay seconds'];
        }

        $runAt = now()->addSeconds($delaySeconds);
        $executionId = $context->metadata['execution_id'] ?? null;

        if (! $executionId) {
            return ['success' => false, 'error' => 'Execution ID required for delay'];
        }

        // Resolve next node (edge-based first, then linear fallback)
        $nextNodeId = BotEdge::where('bot_flow_id', $node->bot_flow_id)
            ->where('from_node_id', $node->id)
            ->orderBy('sort_order')
            ->value('to_node_id');

        if (! $nextNodeId) {
            $nextNodeId = \App\Modules\Chatbots\Models\BotNode::where('bot_flow_id', $node->bot_flow_id)
                ->where('sort_order', '>', $node->sort_order)
                ->orderBy('sort_order')
                ->value('id');
        }

        if (! $nextNodeId) {
            return ['success' => false, 'error' => 'No action node found after delay'];
        }

        // Create action job record
        $actionJob = \App\Modules\Chatbots\Models\BotActionJob::create([
            'account_id' => $context->account->id,
            'bot_execution_id' => $executionId,
            'node_id' => $nextNodeId, // Store the next node to execute
            'run_at' => $runAt,
            'status' => 'queued']);

        // Dispatch queued job
        \App\Modules\Chatbots\Jobs\ProcessDelayedAction::dispatch($actionJob->id)
            ->delay($runAt);

        return ['success' => true, 'scheduled_at' => $runAt->toIso8601String(), 'job_id' => $actionJob->id];
    }

    protected function callWebhook(array $config, BotContext $context): array
    {
        $url = $config['url'] ?? '';
        $timeout = $config['timeout'] ?? 10;
        $method = $config['method'] ?? 'POST';

        if (empty($url)) {
            return ['success' => false, 'error' => 'Webhook URL required'];
        }

        try {
            $payload = [
                'account_id' => $context->account->id,
                'conversation_id' => $context->conversation->id,
                'message' => [
                    'id' => $context->inboundMessage->id,
                    'text' => $context->inboundMessage->text_body],
                'connection' => [
                    'id' => $context->connection->id,
                    'name' => $context->connection->name]];

            $response = app(\App\Services\SafeWebhookClient::class)->send($url, $method, $payload, (int) $timeout);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => substr($response->body(), 0, 500), // Cap response size
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function automationPayload(BotContext $context, string $actionType, array $payload = []): array
    {
        return array_merge($payload, [
            'automation' => self::BOT_AUTOMATION_SOURCE,
            'bot_execution_id' => $context->metadata['execution_id'] ?? null,
            'action_type' => $actionType,
        ]);
    }
}
