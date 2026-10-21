<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\Account;
use App\Models\PlatformSetting;
use App\Modules\Chatbots\Jobs\ProcessInboundMessageForBots;
use App\Modules\WhatsApp\Events\Inbox\AuditEventAdded;
use App\Modules\WhatsApp\Events\Inbox\ConversationUpdated;
use App\Modules\WhatsApp\Events\Inbox\MessageCreated;
use App\Modules\WhatsApp\Events\Inbox\MessageUpdated;
use App\Modules\WhatsApp\Exceptions\WebhookEventLockedException;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppMetaEventLog;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use App\Services\UsageService;
use App\Services\WorkspaceWebhookDispatcher;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebhookProcessor
{
    public function __construct(
        protected UsageService $usageService,
        protected WorkspaceWebhookDispatcher $webhookDispatcher,
        protected WhatsAppClient $whatsAppClient
    ) {}

    /**
     * Process incoming webhook payload.
     * Uses lock to prevent concurrent processing of the same webhook.
     */
    public function process(array $payload, WhatsAppConnection $connection, ?string $correlationId = null): void
    {
        // Use lock to prevent concurrent webhook processing for the same connection
        $lockKey = "webhook_process:connection:{$connection->id}";
        $lock = Cache::lock($lockKey, 60); // 1 minute lock

        // Meta delivers webhooks concurrently (status bursts); wait briefly for the
        // per-connection lock instead of failing immediately.
        try {
            $acquired = (bool) $lock->block((int) config('whatsapp.webhook.lock_wait_seconds', 15));
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            $acquired = false;
        }

        if (! $acquired) {
            Log::channel('whatsapp')->warning('Webhook processing already in progress', [
                'connection_id' => $connection->id,
                'correlation_id' => $correlationId]);

            throw new WebhookEventLockedException('Webhook processing already in progress for this connection.');
        }

        try {
            $this->performProcess($payload, $connection, $correlationId);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual webhook processing.
     */
    protected function performProcess(array $payload, WhatsAppConnection $connection, ?string $correlationId = null): void
    {
        try {
            DB::beginTransaction();

            $entries = $payload['entry'] ?? [];
            if (empty($entries)) {
                throw new \Exception('Invalid webhook payload: missing entry');
            }

            $messagesProcessed = 0;
            $statusesProcessed = 0;

            foreach ((array) $entries as $entryIndex => $entry) {
                $changes = is_array($entry) && is_array($entry['changes'] ?? null) ? $entry['changes'] : [];
                foreach ($changes as $changeIndex => $change) {
                    if (! is_array($change)) {
                        continue;
                    }
                    $value = is_array($change['value'] ?? null) ? $change['value'] : [];
                    $messages = array_filter((array) ($value['messages'] ?? []), 'is_array');
                    $statuses = array_filter((array) ($value['statuses'] ?? []), 'is_array');
                    $field = $change['field'] ?? null;

                    $this->recordMetaEvent($connection, $field, $value);

                    if ($field === 'message_template_status_update') {
                        $this->processTemplateStatus($value, $connection);

                        continue;
                    }

                    // If payload has phone_number_id, it must match this connection (when one URL is used for multiple numbers)
                    $payloadPhoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                    if ($payloadPhoneNumberId !== null && $connection->phone_number_id !== null && $payloadPhoneNumberId !== $connection->phone_number_id) {
                        Log::channel('whatsapp')->info('Webhook change skipped: phone_number_id does not match connection', [
                            'correlation_id' => $correlationId,
                            'connection_id' => $connection->id,
                            'payload_phone_number_id' => $payloadPhoneNumberId,
                            'connection_phone_number_id' => $connection->phone_number_id,
                        ]);

                        continue;
                    }

                    Log::channel('whatsapp')->info('Webhook processing change', [
                        'correlation_id' => $correlationId,
                        'connection_id' => $connection->id,
                        'entry_index' => $entryIndex,
                        'change_index' => $changeIndex,
                        'field' => $field,
                        'messages_count' => count($messages),
                        'statuses_count' => count($statuses),
                    ]);

                    foreach ($messages as $messageData) {
                        $this->processMessage($messageData, $value, $connection, $correlationId);
                        $messagesProcessed++;
                    }

                    foreach ($statuses as $statusData) {
                        $this->processStatus($statusData, $connection);
                        $statusesProcessed++;
                    }

                    if (empty($messages) && empty($statuses) && $field !== 'messages') {
                        $this->processGenericMetaEvent($field, $value, $connection);
                    }
                }
            }

            // Update connection webhook status
            $connection->update([
                'webhook_last_received_at' => now(),
                'webhook_last_error' => null]);

            DB::commit();

            Log::channel('whatsapp')->info('Webhook processed successfully', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'messages_count' => $messagesProcessed,
                'statuses_count' => $statusesProcessed]);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Truncate error message to prevent huge logs
            $errorMessage = substr($e->getMessage(), 0, 500);

            Log::channel('whatsapp')->error('Webhook processing failed', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'error' => $errorMessage,
                'payload_size' => strlen(json_encode($payload))]);

            try {
                $connection->update([
                    'webhook_last_error' => $errorMessage]);
            } catch (\Throwable $updateError) {
                Log::channel('whatsapp')->warning('Webhook error state update failed', [
                    'correlation_id' => $correlationId,
                    'connection_id' => $connection->id,
                    'error' => $updateError->getMessage(),
                ]);
            }

            if (! $this->isTransientDatabaseConcurrencyError($e)) {
                app(\App\Services\AppNotificationService::class)->platform(
                    'failed_webhook',
                    'WhatsApp webhook failed',
                    "Connection {$connection->name}: {$errorMessage}",
                    'critical',
                    null,
                    ['connection_id' => $connection->id, 'account_id' => $connection->account_id, 'correlation_id' => $correlationId, 'dedupe_key' => 'connection_'.$connection->id]
                );
            }

            throw $e;
        }
    }

    protected function isTransientDatabaseConcurrencyError(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Deadlock found')
            || str_contains($message, 'Serialization failure')
            || str_contains($message, 'Lock wait timeout exceeded');
    }

    /**
     * Process a single message from webhook.
     */
    protected function processMessage(array $messageData, array $value, WhatsAppConnection $connection, ?string $correlationId = null): void
    {
        $metaMessageId = $messageData['id'] ?? null;
        if (! $metaMessageId) {
            return;
        }

        $eventKey = "message:{$metaMessageId}";
        if (! $this->claimWebhookEvent($connection, $eventKey, 'message', [
            'correlation_id' => $correlationId,
            'type' => $messageData['type'] ?? null,
            'raw_payload' => [
                'kind' => 'message',
                'message' => $messageData,
                'value' => $value,
            ],
        ])) {
            return;
        }

        // Check if message already exists (idempotency) - use lock to prevent race conditions
        $messageLockKey = "webhook_message:{$metaMessageId}";
        $messageLock = Cache::lock($messageLockKey, 30);

        if (! $messageLock->get()) {
            $this->markWebhookEvent($connection, $eventKey, 'failed', 'Message is already being processed.');
            throw new WebhookEventLockedException('Message is already being processed.');
        }

        try {
            $existingMessage = WhatsAppMessage::where('account_id', $connection->account_id)
                ->where('meta_message_id', $metaMessageId)
                ->lockForUpdate() // Row-level lock
                ->first();

            if ($existingMessage) {
                // Message already processed, skip
                $this->markWebhookEvent($connection, $eventKey, 'skipped', 'Message already exists.');

                return;
            }

            $contactIdentity = $this->extractWhatsAppContactIdentity($value, $messageData);
            $fromWaId = $contactIdentity['wa_id'] ?? null;
            if (! $fromWaId) {
                $this->markWebhookEvent($connection, $eventKey, 'skipped', 'Missing sender wa_id.');

                return;
            }

            $contactName = isset($value['contacts'][0]['profile']['name'])
                ? $value['contacts'][0]['profile']['name']
                : null;

            // Get or create contact with lock to prevent conflicts with ContactService
            $ctwa = $this->extractCtwaReferral($messageData);

            $contact = DB::transaction(function () use ($connection, $fromWaId, $contactName, $ctwa, $contactIdentity) {
                $contact = WhatsAppContact::withTrashed()
                    ->lockForUpdate()
                    ->where('account_id', $connection->account_id)
                    ->where(function ($query) use ($fromWaId, $contactIdentity) {
                        $query->where('wa_id', $fromWaId);

                        if (! empty($contactIdentity['business_scoped_user_id'])) {
                            $query->orWhere('business_scoped_user_id', $contactIdentity['business_scoped_user_id']);
                        }

                        if (! empty($contactIdentity['phone_wa_id'])) {
                            $query->orWhere('wa_id', $contactIdentity['phone_wa_id']);
                        }
                    })
                    ->first();

                if (! $contact) {
                    $contact = new WhatsAppContact([
                        'account_id' => $connection->account_id,
                        'wa_id' => $contactIdentity['phone_wa_id'] ?: $fromWaId,
                    ]);
                }

                if ($contact->exists && method_exists($contact, 'trashed') && $contact->trashed()) {
                    $contact->restore();
                }

                $updates = [
                    'business_scoped_user_id' => $contactIdentity['business_scoped_user_id'] ?: $contact->business_scoped_user_id,
                    'parent_business_scoped_user_id' => $contactIdentity['parent_business_scoped_user_id'] ?: $contact->parent_business_scoped_user_id,
                    'whatsapp_username' => $contactIdentity['whatsapp_username'] ?: $contact->whatsapp_username,
                    'phone' => $contactIdentity['phone_wa_id'] ?: $contact->phone,
                ];

                // Prefer a real phone as wa_id once known so outbound Cloud API sends stay addressable.
                if (! empty($contactIdentity['phone_wa_id'])) {
                    $updates['wa_id'] = $contactIdentity['phone_wa_id'];
                }

                if (! $contact->exists) {
                    $updates['name'] = $contactName;
                    $updates['source'] = $ctwa ? 'ctwa' : 'webhook';
                }

                $contact->fill(array_filter($updates, fn ($value) => $value !== null && $value !== ''));
                $contact->save();

                if ($ctwa) {
                    $metadata = is_array($contact->metadata) ? $contact->metadata : [];
                    $metadata['ctwa'] = $this->mergeCtwaMetadata($metadata['ctwa'] ?? null, $ctwa);
                    $contact->forceFill([
                        'source' => $contact->source === 'webhook' || blank($contact->source) ? 'ctwa' : $contact->source,
                        'metadata' => $metadata,
                    ])->save();
                }

                return $contact;
            });

            // Update contact name if provided (with lock)
            if ($contactName !== null) {
                $contact->lockForUpdate();
                $contact->update(['name' => $contactName]);
            }

            // Get or create conversation
            $conversation = WhatsAppConversation::lockForUpdate()
                ->firstOrCreate(
                    [
                        'account_id' => $connection->account_id,
                        'whatsapp_connection_id' => $connection->id,
                        'whatsapp_contact_id' => $contact->id],
                    [
                        'status' => 'open']
                );

            $messageType = $messageData['type'] ?? 'text';
            $textBody = $this->extractMessageText($messageData, $messageType);
            $payload = $this->enrichInboundPayload($messageData, $messageType, $connection, $correlationId);
            $payload = $this->enrichReplyContext($payload, $conversation);
            if ($ctwa) {
                $payload['ctwa'] = $ctwa;
                $conversationMetadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $conversationMetadata['ctwa'] = $this->mergeCtwaMetadata($conversationMetadata['ctwa'] ?? null, $ctwa);
                $conversation->forceFill(['metadata' => $conversationMetadata])->save();
            }

            // Create message
            $message = WhatsAppMessage::create([
                'account_id' => $connection->account_id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'meta_message_id' => $metaMessageId,
                'type' => $messageType,
                'text_body' => $textBody,
                'payload' => $payload,
                'status' => 'delivered', // Inbound messages are considered delivered
                'received_at' => now()]);

            Log::channel('whatsapp')->info('Inbound message created', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'meta_message_id' => $metaMessageId,
                'from' => $fromWaId,
                'type' => $messageType,
            ]);

            // Update conversation
            $conversation->update([
                'last_message_at' => now(),
                'last_message_preview' => $textBody ? substr($textBody, 0, 100) : "[{$messageType}]"]);

            // Contact message_count is bumped by the WhatsAppMessage "created" hook.
            $contact->forceFill([
                'last_seen_at' => now(),
            ])->save();

            // Auto-assign if enabled and conversation is unassigned
            $this->autoAssignConversation($conversation, $connection);

            // Load relationships for broadcast
            $message->load('conversation.contact');
            $conversation->load('contact');

            // Broadcast events
            $this->broadcastSafely(new MessageCreated($message));
            $this->broadcastSafely(new ConversationUpdated($conversation));

            $keyword = app(OptOutKeywordService::class)->classify($textBody);
            if ($keyword !== null) {
                $this->applyOptOutKeyword($keyword, $contact, $conversation, $connection, $message, $correlationId);
                $this->markWebhookEvent($connection, $eventKey, 'processed');

                return;
            }

            $isNewConversation = $conversation->wasRecentlyCreated;
            DB::afterCommit(function () use ($conversation, $connection, $contact, $isNewConversation, $correlationId) {
                $this->maybeSendWelcomeMessage($conversation, $connection, $contact, $isNewConversation, $correlationId);
            });

            $this->webhookDispatcher->dispatch($connection->account_id, 'message.received', [
                'message' => $this->messagePayload($message),
                'conversation_id' => $conversation->id,
                'contact' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'wa_id' => $contact->wa_id,
                    'phone' => $contact->phone,
                ],
                'connection_id' => $connection->id,
                'ctwa' => $ctwa,
            ]);

            // Process bots for inbound messages (queued to prevent webhook timeout)
            $account = $conversation->account ?? $connection->account ?? Account::find($connection->account_id);
            if (! $account) {
                Log::channel('whatsapp')->warning('Skipping bot dispatch: account not resolved', [
                    'correlation_id' => $correlationId,
                    'connection_id' => $connection->id,
                    'connection_account_id' => $connection->account_id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                ]);

                return;
            }

            $chatbotsEnabled = $message->direction === 'inbound' && module_enabled($account, 'automation.chatbots');
            Log::channel('chatbots')->debug('Inbound message bot dispatch check', [
                'correlation_id' => $correlationId,
                'account_id' => $account->id,
                'connection_id' => $connection->id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'meta_message_id' => $metaMessageId,
                'enabled' => $chatbotsEnabled,
            ]);

            $aiEnabled = $message->direction === 'inbound' && module_enabled($account, 'ai');
            if ($message->direction === 'inbound' && ($chatbotsEnabled || $aiEnabled)) {
                DB::afterCommit(function () use ($message, $conversation) {
                    ProcessInboundMessageForBots::dispatchAfterResponse($message, $conversation);
                });
            }
            $this->markWebhookEvent($connection, $eventKey, 'processed');
        } catch (\Throwable $e) {
            $this->markWebhookEvent($connection, $eventKey, 'failed', $e->getMessage());

            throw $e;
        } finally {
            $messageLock->release();
        }
    }

    protected function applyOptOutKeyword(
        string $action,
        WhatsAppContact $contact,
        WhatsAppConversation $conversation,
        WhatsAppConnection $connection,
        WhatsAppMessage $inbound,
        ?string $correlationId = null
    ): void {
        $status = $action === 'stop' ? 'opt_out' : 'active';
        $contact->forceFill(['status' => $status])->save();

        $payload = is_array($inbound->payload) ? $inbound->payload : [];
        $payload['opt_out_keyword'] = $action;
        $inbound->forceFill(['payload' => $payload])->save();

        $body = app(OptOutKeywordService::class)->reply($action);
        $reply = WhatsAppMessage::create([
            'account_id' => $connection->account_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'text_body' => $body,
            'payload' => [
                'automation' => 'opt_out_keyword',
                'action' => $action,
            ],
            'status' => 'queued',
        ]);

        $reply->load('conversation.contact');
        $this->broadcastSafely(new MessageCreated($reply));

        try {
            $response = $this->whatsAppClient->sendTextMessage($connection, $contact->wa_id, $body);
            $metaMessageId = $response['messages'][0]['id'] ?? null;
            $reply->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($reply->payload ?? [], [
                    'response' => $response,
                ]),
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($body, 100, ''),
            ])->save();

            $account = $conversation->account ?? $connection->account ?? Account::find($connection->account_id);
            if ($account) {
                $this->usageService->incrementMessages($account, 1);
            }

            $this->broadcastSafely(new MessageUpdated($reply));
            $this->broadcastSafely(new ConversationUpdated($conversation->fresh('contact')));
        } catch (\Throwable $e) {
            $reply->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $this->broadcastSafely(new MessageUpdated($reply));

            Log::channel('whatsapp')->warning('Opt-out keyword auto-reply failed', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'contact_id' => $contact->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function maybeSendWelcomeMessage(
        WhatsAppConversation $conversation,
        WhatsAppConnection $connection,
        WhatsAppContact $contact,
        bool $isNewConversation,
        ?string $correlationId = null
    ): void {
        if (! $isNewConversation) {
            return;
        }

        $account = $conversation->account ?? $connection->account ?? Account::find($connection->account_id);
        if (! $account || ! $account->welcome_message_enabled || blank($account->welcome_message_body)) {
            return;
        }

        $conversation->refresh();
        $metadata = $conversation->metadata ?? [];
        if (! empty($metadata['welcome_message_sent_at'])) {
            return;
        }

        $body = $this->renderWelcomeMessage((string) $account->welcome_message_body, $account, $contact);
        if (blank($body)) {
            return;
        }

        $message = WhatsAppMessage::create([
            'account_id' => $connection->account_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'text_body' => $body,
            'payload' => [
                'automation' => 'welcome_message',
                'trigger' => 'new_conversation',
            ],
            'status' => 'queued',
        ]);

        $message->load('conversation.contact');
        $this->broadcastSafely(new MessageCreated($message));

        try {
            $response = $this->whatsAppClient->sendTextMessage($connection, $contact->wa_id, $body);
            $metaMessageId = $response['messages'][0]['id'] ?? null;

            $message->update([
                'meta_message_id' => $metaMessageId,
                'status' => 'sent',
                'sent_at' => now(),
                'payload' => array_merge($message->payload ?? [], [
                    'response' => $response,
                ]),
            ]);

            $conversation->forceFill([
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($body, 100, ''),
                'metadata' => array_merge($metadata, [
                    'welcome_message_sent_at' => now()->toISOString(),
                    'welcome_message_id' => $message->id,
                ]),
            ])->save();

            $this->usageService->incrementMessages($account, 1);

            $this->broadcastSafely(new MessageUpdated($message));
            $this->broadcastSafely(new ConversationUpdated($conversation->fresh('contact')));
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $conversation->forceFill([
                'metadata' => array_merge($metadata, [
                    'welcome_message_error' => Str::limit($e->getMessage(), 500, ''),
                    'welcome_message_error_at' => now()->toISOString(),
                ]),
            ])->save();

            $this->broadcastSafely(new MessageUpdated($message));

            Log::channel('whatsapp')->warning('Welcome message automation failed', [
                'correlation_id' => $correlationId,
                'account_id' => $connection->account_id,
                'connection_id' => $connection->id,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function renderWelcomeMessage(string $body, Account $account, WhatsAppContact $contact): string
    {
        return strtr($body, [
            '{{name}}' => $contact->name ?: 'there',
            '{{phone}}' => $contact->phone ?: $contact->wa_id,
            '{{workspace}}' => $account->name,
        ]);
    }

    protected function extractCtwaReferral(array $messageData): ?array
    {
        $referral = $messageData['referral']
            ?? $messageData['context']['referral']
            ?? $messageData['context']['ad']
            ?? null;

        if (! is_array($referral) || $referral === []) {
            return null;
        }

        $sourceType = $referral['source_type'] ?? $referral['source'] ?? 'ad';
        $sourceId = $referral['source_id'] ?? $referral['ad_id'] ?? $referral['post_id'] ?? null;
        $sourceUrl = $referral['source_url'] ?? $referral['url'] ?? null;
        $ctwaClid = $referral['ctwa_clid'] ?? $referral['ctwaClid'] ?? $referral['click_id'] ?? null;
        $headline = $referral['headline'] ?? $referral['title'] ?? $referral['ad_title'] ?? null;
        $body = $referral['body'] ?? $referral['description'] ?? $referral['ad_body'] ?? null;

        return [
            'source' => 'click_to_whatsapp_ad',
            'source_type' => is_scalar($sourceType) ? (string) $sourceType : 'ad',
            'source_id' => is_scalar($sourceId) ? (string) $sourceId : null,
            'source_url' => is_scalar($sourceUrl) ? (string) $sourceUrl : null,
            'ctwa_clid' => is_scalar($ctwaClid) ? (string) $ctwaClid : null,
            'headline' => is_scalar($headline) ? (string) $headline : null,
            'body' => is_scalar($body) ? (string) $body : null,
            'media_type' => isset($referral['image']) ? 'image' : (isset($referral['video']) ? 'video' : null),
            'media' => [
                'image' => is_array($referral['image'] ?? null) ? $referral['image'] : null,
                'video' => is_array($referral['video'] ?? null) ? $referral['video'] : null,
            ],
            'captured_at' => now()->toIso8601String(),
            'raw' => $referral,
        ];
    }

    protected function mergeCtwaMetadata(mixed $existing, array $ctwa): array
    {
        $existing = is_array($existing) ? $existing : [];
        $history = is_array($existing['history'] ?? null) ? $existing['history'] : [];
        $fingerprint = $ctwa['ctwa_clid'] ?: ($ctwa['source_id'] ?: md5((string) json_encode($ctwa)));
        $duplicate = collect($history)->contains(fn ($item) => is_array($item) && (($item['fingerprint'] ?? null) === $fingerprint));

        if (! $duplicate) {
            $history[] = $ctwa + ['fingerprint' => $fingerprint];
        }

        return [
            'latest' => $ctwa,
            'first' => $existing['first'] ?? $ctwa,
            'history' => array_slice($history, -10),
        ];
    }

    /**
     * Process a single status update from webhook.
     * Handles status updates for both regular messages and campaign messages.
     */
    protected function processStatus(array $statusData, WhatsAppConnection $connection): void
    {
        $metaMessageId = $statusData['id'] ?? null;
        if (! $metaMessageId) {
            return;
        }

        $status = $statusData['status'] ?? 'unknown';
        $timestamp = $statusData['timestamp'] ?? '';
        $eventKey = "status:{$metaMessageId}:{$status}:{$timestamp}";
        if (! $this->claimWebhookEvent($connection, $eventKey, 'status', [
            'status' => $status,
            'timestamp' => $timestamp,
            'raw_payload' => [
                'kind' => 'status',
                'status' => $statusData,
            ],
        ])) {
            return;
        }

        // Use lock to prevent concurrent status updates
        $statusLockKey = "webhook_status:{$metaMessageId}";
        $statusLock = Cache::lock($statusLockKey, 30);

        if (! $statusLock->get()) {
            $this->markWebhookEvent($connection, $eventKey, 'failed', 'Status is already being processed.');
            throw new WebhookEventLockedException('Status is already being processed.');
        }

        try {
            // Check both regular messages and campaign messages
            $message = WhatsAppMessage::where('account_id', $connection->account_id)
                ->where('meta_message_id', $metaMessageId)
                ->lockForUpdate()
                ->first();

            // Also check campaign messages
            $campaignMessage = null;
            if (! $message) {
                $campaignMessage = \App\Modules\Broadcasts\Models\CampaignMessage::where('wamid', $metaMessageId)
                    ->whereHas('campaign', fn ($query) => $query->where('account_id', $connection->account_id))
                    ->lockForUpdate()
                    ->first();
            }

            if (! $message && ! $campaignMessage) {
                $this->markWebhookEvent($connection, $eventKey, 'skipped', 'Message not found for status update.');
                Log::channel('whatsapp')->info('Skipped status update for unknown Meta message', [
                    'connection_id' => $connection->id,
                    'account_id' => $connection->account_id,
                    'meta_message_id' => $metaMessageId,
                    'status' => $status,
                ]);

                return;
            }

            $timestamp = isset($statusData['timestamp']) ? (int) $statusData['timestamp'] : null;
            $statusAt = $timestamp ? now()->setTimestamp($timestamp) : now();

            if ($message) {
                // Update regular message
                // Meta does not guarantee ordering: never move a message backwards
                // (e.g. a late "sent" after "read"). "failed" always applies.
                $keepStatus = $status !== 'failed'
                    && $this->messageStatusRank($status) < $this->messageStatusRank($message->status)
                    && $this->messageStatusRank($status) >= 0;
                $updates = [
                    'status' => $keepStatus ? $message->status : ($status ?: $message->status)];

                if ($status === 'sent') {
                    $updates['sent_at'] = $statusAt;
                } elseif ($status === 'delivered') {
                    $updates['delivered_at'] = $statusAt;
                } elseif ($status === 'read') {
                    $updates['read_at'] = $statusAt;
                } elseif ($status === 'failed') {
                    $errors = $statusData['errors'][0] ?? null;
                    $updates['error_message'] = $errors['title'] ?? $errors['message'] ?? 'Delivery failed';
                }

                $message->update($updates);
                $message->refresh();

                \App\Modules\WhatsApp\Models\WhatsAppTemplateSend::where('whatsapp_message_id', $message->id)
                    ->update([
                        'status' => $updates['status'],
                        'error_message' => $updates['error_message'] ?? null,
                    ]);

                $this->broadcastSafely(new \App\Modules\WhatsApp\Events\Inbox\MessageUpdated($message));

                if (in_array($status, ['delivered', 'read'], true)) {
                    $this->webhookDispatcher->dispatch($connection->account_id, 'message.'.$status, [
                        'message' => $this->messagePayload($message),
                        'connection_id' => $connection->id,
                        'status_payload' => $statusData,
                    ]);
                }
            } elseif ($campaignMessage) {
                // Update campaign message via service to maintain consistency
                $campaignService = app(\App\Modules\Broadcasts\Services\CampaignService::class);
                $failureReason = null;
                if ($status === 'failed') {
                    $errors = $statusData['errors'][0] ?? null;
                    $failureReason = $errors['title']
                        ?? $errors['message']
                        ?? $errors['error_data']['details']
                        ?? null;
                }

                $campaignService->updateMessageStatus($metaMessageId, $status, $statusAt, $failureReason);
            }

            $this->recordMetaBillingUsage(
                statusData: $statusData,
                connection: $connection,
                message: $message,
                campaignMessage: $campaignMessage,
                metaMessageId: $metaMessageId,
            );
            $this->markWebhookEvent($connection, $eventKey, 'processed');
        } catch (\Throwable $e) {
            $this->markWebhookEvent($connection, $eventKey, 'failed', $e->getMessage());

            throw $e;
        } finally {
            $statusLock->release();
        }
    }

    /**
     * Broadcast realtime inbox events only after the webhook transaction commits,
     * and never let a Pusher outage roll back (and permanently lose) a webhook.
     */
    protected function broadcastSafely(object $event): void
    {
        DB::afterCommit(function () use ($event) {
            try {
                event($event);
            } catch (\Throwable $e) {
                Log::channel('whatsapp')->warning('Realtime broadcast failed (webhook data kept)', [
                    'event' => get_class($event),
                    'error' => substr($e->getMessage(), 0, 300),
                ]);
            }
        });
    }

    protected function messageStatusRank(?string $status): int
    {
        return match (strtolower((string) $status)) {
            'pending', 'queued', 'accepted' => 0,
            'sent' => 1,
            'delivered' => 2,
            'read', 'played' => 3,
            default => -1,
        };
    }

    protected function claimWebhookEvent(WhatsAppConnection $connection, string $eventKey, string $eventType, array $meta = []): bool
    {
        if (! SchemaCache::hasTable('whatsapp_webhook_events')) {
            return true;
        }

        $now = now();
        $inserted = DB::table('whatsapp_webhook_events')->insertOrIgnore([
            'account_id' => $connection->account_id,
            'whatsapp_connection_id' => $connection->id,
            'event_key' => $eventKey,
            'event_type' => $eventType,
            'status' => 'processing',
            'attempts' => 1,
            'first_received_at' => $now,
            'last_received_at' => $now,
            'meta' => $meta ? json_encode($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return true;
        }

        return DB::transaction(function () use ($connection, $eventKey, $eventType, $meta, $now) {
            $existing = DB::table('whatsapp_webhook_events')
                ->where('account_id', $connection->account_id)
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if (! $existing) {
                return false;
            }

            $updatedAt = $existing->updated_at ? \Illuminate\Support\Carbon::parse($existing->updated_at) : null;
            $canRetry = $existing->status === 'failed'
                || ($existing->status === 'processing' && $updatedAt?->lte($now->copy()->subMinutes(10)));

            if ($canRetry) {
                DB::table('whatsapp_webhook_events')
                    ->where('id', $existing->id)
                    ->update([
                        'whatsapp_connection_id' => $connection->id,
                        'event_type' => $eventType,
                        'status' => 'processing',
                        'attempts' => DB::raw('attempts + 1'),
                        'last_received_at' => $now,
                        'processed_at' => null,
                        'last_error' => null,
                        'meta' => $meta ? json_encode($meta) : $existing->meta,
                        'updated_at' => $now,
                    ]);

                return true;
            }

            DB::table('whatsapp_webhook_events')
                ->where('id', $existing->id)
                ->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'last_received_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($existing->status === 'processing') {
                throw new WebhookEventLockedException("Webhook event {$eventKey} is already processing.");
            }

            return false;
        });
    }

    protected function markWebhookEvent(WhatsAppConnection $connection, string $eventKey, string $status, ?string $error = null): void
    {
        if (! SchemaCache::hasTable('whatsapp_webhook_events')) {
            return;
        }

        DB::table('whatsapp_webhook_events')
            ->where('account_id', $connection->account_id)
            ->where('event_key', $eventKey)
            ->update([
                'status' => $status,
                'processed_at' => in_array($status, ['processed', 'skipped'], true) ? now() : null,
                'last_error' => $error ? mb_substr($error, 0, 1000) : null,
                'updated_at' => now(),
            ]);
    }

    public function replayWebhookEvent(int $eventId): void
    {
        if (! SchemaCache::hasTable('whatsapp_webhook_events')) {
            throw new \RuntimeException('Webhook event table is not available.');
        }

        $event = DB::table('whatsapp_webhook_events')->where('id', $eventId)->first();
        if (! $event) {
            throw new \RuntimeException('Webhook event not found.');
        }

        $connection = WhatsAppConnection::find($event->whatsapp_connection_id);
        if (! $connection) {
            throw new \RuntimeException('Webhook connection is no longer available.');
        }

        $meta = json_decode((string) ($event->meta ?? ''), true) ?: [];
        $payload = $meta['raw_payload'] ?? null;
        if (! is_array($payload)) {
            throw new \RuntimeException('This webhook event has no replay payload. Only newly captured events can be replayed.');
        }

        DB::table('whatsapp_webhook_events')
            ->where('id', $event->id)
            ->update([
                'status' => 'failed',
                'last_error' => 'Queued for manual replay.',
                'updated_at' => now(),
            ]);

        if (($payload['kind'] ?? null) === 'message' && is_array($payload['message'] ?? null) && is_array($payload['value'] ?? null)) {
            $this->processMessage($payload['message'], $payload['value'], $connection, 'manual-replay-'.$event->id);

            return;
        }

        if (($payload['kind'] ?? null) === 'status' && is_array($payload['status'] ?? null)) {
            $this->processStatus($payload['status'], $connection);

            return;
        }

        throw new \RuntimeException('Unsupported webhook replay payload.');
    }

    protected function extractMessageText(array $messageData, string $messageType): ?string
    {
        if ($messageType === 'text') {
            return $messageData['text']['body'] ?? null;
        }

        if ($messageType === 'button') {
            return $messageData['button']['text'] ?? $messageData['button']['payload'] ?? null;
        }

        if ($messageType === 'interactive') {
            return $messageData['interactive']['button_reply']['title']
                ?? $messageData['interactive']['list_reply']['title']
                ?? $messageData['interactive']['nfm_reply']['body']
                ?? $messageData['interactive']['nfm_reply']['name']
                ?? null;
        }

        if ($messageType === 'order') {
            $productItems = $messageData['order']['product_items'] ?? [];
            $count = is_array($productItems) ? count($productItems) : 0;

            return $count > 0
                ? "Catalog order received ({$count} item".($count === 1 ? '' : 's').')'
                : 'Catalog order received';
        }

        if (in_array($messageType, ['image', 'video', 'document'], true)) {
            return $messageData[$messageType]['caption'] ?? null;
        }

        if ($messageType === 'audio') {
            return ($messageData['audio']['voice'] ?? false) ? 'Voice message' : 'Audio message';
        }

        if ($messageType === 'location') {
            return $messageData['location']['name']
                ?? $messageData['location']['address']
                ?? 'Location shared';
        }

        if ($messageType === 'contacts') {
            $contact = $messageData['contacts'][0] ?? [];

            return $contact['name']['formatted_name'] ?? 'Contact shared';
        }

        if ($messageType === 'reaction') {
            return $messageData['reaction']['emoji'] ?? 'Reaction';
        }

        if ($messageType === 'sticker') {
            return 'Sticker';
        }

        return null;
    }

    protected function enrichInboundPayload(array $messageData, string $messageType, WhatsAppConnection $connection, ?string $correlationId = null): array
    {
        $payload = $messageData;
        if (! in_array($messageType, ['image', 'video', 'document', 'audio', 'sticker'], true)) {
            return $payload;
        }

        $mediaId = $messageData[$messageType]['id'] ?? null;
        if (! $mediaId) {
            return $payload;
        }

        try {
            $download = $this->whatsAppClient->downloadMedia($connection, (string) $mediaId);
            $extension = $this->extensionFromMime((string) ($download['mime_type'] ?? 'application/octet-stream'), $messageType);
            $path = sprintf(
                'whatsapp-inbound/%d/%s.%s',
                $connection->account_id,
                Str::slug((string) $mediaId) ?: Str::random(24),
                $extension
            );

            Storage::disk('public')->put($path, $download['body']);
            $localUrl = Storage::disk('public')->url($path);
            if (! str_starts_with($localUrl, 'http://') && ! str_starts_with($localUrl, 'https://')) {
                $localUrl = rtrim(config('app.url'), '/').'/'.ltrim($localUrl, '/');
            }

            $payload['media'] = [
                'id' => $mediaId,
                'type' => $messageType,
                'mime_type' => $download['mime_type'] ?? ($messageData[$messageType]['mime_type'] ?? null),
                'sha256' => $download['sha256'] ?? ($messageData[$messageType]['sha256'] ?? null),
                'file_size' => $download['file_size'] ?? null,
                'local_path' => $path,
                'local_url' => $localUrl,
                'meta_url' => $download['url'] ?? null,
            ];
            $payload[$messageType]['link'] = $localUrl;
            $payload[$messageType]['url'] = $localUrl;
        } catch (\Throwable $e) {
            $payload['media'] = [
                'id' => $mediaId,
                'type' => $messageType,
                'download_error' => $e->getMessage(),
            ];
            Log::channel('whatsapp')->warning('Inbound media download failed', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'media_id' => $mediaId,
                'type' => $messageType,
                'error' => $e->getMessage(),
            ]);
        }

        return $payload;
    }

    protected function enrichReplyContext(array $payload, WhatsAppConversation $conversation): array
    {
        $metaMessageId = $payload['context']['id'] ?? null;
        if (! $metaMessageId) {
            return $payload;
        }

        $replyMessage = WhatsAppMessage::where('account_id', $conversation->account_id)
            ->where('whatsapp_conversation_id', $conversation->id)
            ->where('meta_message_id', $metaMessageId)
            ->first();

        if (! $replyMessage) {
            return $payload;
        }

        $preview = $replyMessage->text_body ?: match ($replyMessage->type) {
            'audio' => ($replyMessage->payload['voice'] ?? false) ? 'Voice message' : 'Audio message',
            'image' => 'Image message',
            'video' => 'Video message',
            'document' => $replyMessage->payload['filename'] ?? 'Document message',
            'location' => 'Location message',
            default => ucfirst((string) $replyMessage->type).' message',
        };

        $payload['context']['message_id'] = $replyMessage->id;
        $payload['context']['preview'] = Str::limit((string) $preview, 160, '');

        return $payload;
    }

    protected function extensionFromMime(string $mimeType, string $messageType): string
    {
        return match (strtolower(strtok($mimeType, ';') ?: $mimeType)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/ogg' => 'ogg',
            'application/pdf' => 'pdf',
            default => match ($messageType) {
                'image', 'sticker' => 'webp',
                'video' => 'mp4',
                'audio' => 'ogg',
                'document' => 'bin',
                default => 'bin',
            },
        };
    }

    protected function processTemplateStatus(array $value, WhatsAppConnection $connection): void
    {
        $metaTemplateId = $value['message_template_id'] ?? $value['id'] ?? null;
        $templateName = $value['message_template_name'] ?? $value['name'] ?? null;
        $event = $value['event'] ?? $value['status'] ?? null;

        if (! $metaTemplateId && ! $templateName) {
            return;
        }

        $status = $event ? strtolower((string) $event) : null;
        $reason = $value['reason'] ?? $value['rejection_reason'] ?? null;

        $query = WhatsAppTemplate::where('account_id', $connection->account_id)
            ->where('whatsapp_connection_id', $connection->id);

        if ($metaTemplateId) {
            $query->where('meta_template_id', (string) $metaTemplateId);
        } elseif ($templateName) {
            $query->where('name', (string) $templateName);
        }

        $template = $query->first();
        if (! $template) {
            Log::channel('whatsapp')->info('Template status webhook ignored: template not found', [
                'connection_id' => $connection->id,
                'meta_template_id' => $metaTemplateId,
                'template_name' => $templateName,
                'event' => $event,
            ]);

            return;
        }

        $template->update([
            'status' => $status ?: $template->status,
            'last_synced_at' => now(),
            'last_meta_error' => $reason,
        ]);

        if ($status === 'rejected') {
            app(\App\Services\AppNotificationService::class)->workspace(
                $connection->account_id,
                'template_rejected',
                'Template rejected by Meta',
                "{$template->name}: ".($reason ?: 'No rejection reason provided.'),
                'warning',
                route('app.whatsapp.templates.index', ['template' => $template->slug]),
                ['template_id' => $template->id, 'reason' => $reason, 'dedupe_key' => 'template_'.$template->id]
            );
        }

        Log::channel('whatsapp')->info('Template status updated from webhook', [
            'connection_id' => $connection->id,
            'template_id' => $template->id,
            'meta_template_id' => $metaTemplateId,
            'status' => $status,
            'reason' => $reason,
        ]);

        $this->webhookDispatcher->dispatch($connection->account_id, 'template.status_updated', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'language' => $template->language,
                'status' => $template->status,
                'reason' => $reason,
            ],
            'connection_id' => $connection->id,
        ]);
    }

    protected function processGenericMetaEvent(?string $field, array $value, WhatsAppConnection $connection): void
    {
        $event = strtolower((string) ($value['event'] ?? $value['status'] ?? $value['type'] ?? $field ?? 'meta_event'));

        if (str_contains((string) $field, 'phone') || str_contains($event, 'quality')) {
            $updates = array_filter([
                'quality_rating' => $value['quality_rating'] ?? $value['current_limit'] ?? null,
                'phone_number_status' => $value['status'] ?? null,
                'calling_last_checked_at' => now(),
            ], fn ($item) => $item !== null && $item !== '');

            if ($updates) {
                $connection->forceFill($updates)->save();
            }
        }

        if (str_contains((string) $field, 'account') || str_contains($event, 'account')) {
            $updates = array_filter([
                'meta_account_review_status' => $value['account_review_status'] ?? $value['status'] ?? null,
                'meta_business_verification_status' => $value['business_verification_status'] ?? null,
            ], fn ($item) => $item !== null && $item !== '');

            if ($updates) {
                $connection->forceFill($updates)->save();
            }
        }

        $this->webhookDispatcher->dispatch($connection->account_id, 'meta.'.$event, [
            'connection_id' => $connection->id,
            'field' => $field,
            'payload' => $value,
        ]);
    }

    protected function recordMetaEvent(WhatsAppConnection $connection, ?string $field, array $value): void
    {
        if (! SchemaCache::hasTable('whatsapp_meta_event_logs')) {
            return;
        }

        $status = $value['status']
            ?? $value['event']
            ?? $value['message_template_status']
            ?? $value['quality_rating']
            ?? null;

        $objectId = $value['id']
            ?? $value['message_template_id']
            ?? $value['phone_number_id']
            ?? $value['metadata']['phone_number_id']
            ?? null;

        WhatsAppMetaEventLog::create([
            'account_id' => $connection->account_id,
            'whatsapp_connection_id' => $connection->id,
            'field' => $field,
            'event_type' => $value['event'] ?? $field ?? 'messages',
            'object_id' => is_scalar($objectId) ? (string) $objectId : null,
            'status' => is_scalar($status) ? (string) $status : null,
            'message' => $value['reason'] ?? $value['rejection_reason'] ?? $value['description'] ?? null,
            'payload' => $value,
            'received_at' => now(),
        ]);
    }

    protected function messagePayload(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id,
            'meta_message_id' => $message->meta_message_id,
            'conversation_id' => $message->whatsapp_conversation_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'text' => $message->text_body,
            'status' => $message->status,
            'sent_at' => $message->sent_at?->toIso8601String(),
            'delivered_at' => $message->delivered_at?->toIso8601String(),
            'read_at' => $message->read_at?->toIso8601String(),
            'received_at' => $message->received_at?->toIso8601String(),
        ];
    }

    protected function recordMetaBillingUsage(
        array $statusData,
        WhatsAppConnection $connection,
        ?WhatsAppMessage $message,
        $campaignMessage,
        string $metaMessageId
    ): void {
        $pricing = is_array($statusData['pricing'] ?? null) ? $statusData['pricing'] : [];
        $conversation = is_array($statusData['conversation'] ?? null) ? $statusData['conversation'] : [];

        $hasBillingHints = ! empty($pricing) || ! empty($conversation);
        if (! $hasBillingHints) {
            return;
        }

        $billable = $this->toBoolean($pricing['billable'] ?? false);
        $category = strtolower((string) ($pricing['category'] ?? $conversation['category'] ?? ''));
        $pricingModel = (string) ($pricing['pricing_model'] ?? '');
        $estimatedCostMinor = $this->estimateMetaConversationCostMinor($billable, $category);

        $billingPayload = [
            'whatsapp_message_id' => $message?->id,
            'campaign_message_id' => $campaignMessage?->id,
            'billable' => $billable,
            'category' => $category ?: null,
            'pricing_model' => $pricingModel ?: null,
            'estimated_cost_minor' => $estimatedCostMinor,
            'meta' => json_encode([
                'pricing' => $pricing,
                'conversation' => $conversation,
                'status' => $statusData['status'] ?? null,
            ]),
            'counted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $inserted = DB::table('whatsapp_message_billings')->insertOrIgnore(array_merge([
            'account_id' => $connection->account_id,
            'meta_message_id' => $metaMessageId,
        ], $billingPayload));

        if ($inserted !== 1) {
            DB::table('whatsapp_message_billings')
                ->where('account_id', $connection->account_id)
                ->where('meta_message_id', $metaMessageId)
                ->update([
                    'whatsapp_message_id' => $message?->id,
                    'campaign_message_id' => $campaignMessage?->id,
                    'meta' => $billingPayload['meta'],
                    'updated_at' => now(),
                ]);

            return;
        }

        $account = $connection->account ?: Account::find($connection->account_id);
        if (! $account) {
            return;
        }

        $this->usageService->incrementMetaConversationUsage(
            account: $account,
            billable: $billable,
            category: $category,
            estimatedCostMinor: $estimatedCostMinor
        );
    }

    protected function estimateMetaConversationCostMinor(bool $billable, string $category): int
    {
        if (! $billable) {
            return 0;
        }

        $key = match ($category) {
            'marketing' => 'whatsapp.meta_billing.rate.marketing_minor',
            'utility' => 'whatsapp.meta_billing.rate.utility_minor',
            'authentication' => 'whatsapp.meta_billing.rate.authentication_minor',
            'service' => 'whatsapp.meta_billing.rate.service_minor',
            default => null,
        };

        if (! $key) {
            return 0;
        }

        return (int) PlatformSetting::get($key, 0);
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    protected function extractWhatsAppContactIdentity(array $value, array $messageData): array
    {
        $contactData = $value['contacts'][0] ?? [];
        $profile = is_array($contactData['profile'] ?? null) ? $contactData['profile'] : [];

        $from = $messageData['from'] ?? null;
        $contactWaId = $contactData['wa_id'] ?? null;
        $businessScopedUserId = $messageData['from_user_id']
            ?? $messageData['user_id']
            ?? $contactData['user_id']
            ?? $contactData['business_scoped_user_id']
            ?? null;
        $parentBusinessScopedUserId = $messageData['parent_user_id']
            ?? $contactData['parent_user_id']
            ?? $contactData['parent_business_scoped_user_id']
            ?? null;
        $username = $messageData['username']
            ?? $contactData['username']
            ?? $profile['username']
            ?? null;

        $phoneWaId = null;
        foreach ([$contactWaId, $from] as $candidate) {
            if (WhatsAppContact::looksLikePhoneIdentifier(is_string($candidate) ? $candidate : null)) {
                $phoneWaId = preg_replace('/\D+/', '', (string) $candidate);
                break;
            }
        }

        $primaryWaId = $phoneWaId
            ?: (is_string($businessScopedUserId) && trim($businessScopedUserId) !== '' ? trim($businessScopedUserId) : null)
            ?: (is_string($contactWaId) && trim($contactWaId) !== '' ? trim($contactWaId) : null)
            ?: (is_string($from) && trim($from) !== '' ? trim($from) : null);

        return [
            'wa_id' => $primaryWaId,
            'phone_wa_id' => $phoneWaId,
            'business_scoped_user_id' => is_string($businessScopedUserId) ? trim($businessScopedUserId) : null,
            'parent_business_scoped_user_id' => is_string($parentBusinessScopedUserId) ? trim($parentBusinessScopedUserId) : null,
            'whatsapp_username' => is_string($username) ? ltrim(trim($username), '@') : null,
        ];
    }

    /**
     * Auto-assign a conversation when enabled.
     */
    protected function autoAssignConversation(WhatsAppConversation $conversation, WhatsAppConnection $connection): void
    {
        if (! Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            return;
        }

        if ($conversation->assigned_to) {
            return;
        }

        $account = $connection->account ?: Account::find($connection->account_id);
        if (! $account || ! $account->auto_assign_enabled) {
            return;
        }

        if (($account->auto_assign_strategy ?? 'round_robin') !== 'round_robin') {
            return;
        }

        $agentIds = $account->getAssignableAgentIds();
        if (empty($agentIds)) {
            return;
        }

        $counterKey = "account:{$account->id}:auto_assign_rr";
        $next = Cache::increment($counterKey);
        if ($next === 1) {
            Cache::put($counterKey, 1, now()->addDays(7));
        }

        $index = ($next - 1) % count($agentIds);
        $assigneeId = $agentIds[$index] ?? null;

        if (! $assigneeId) {
            return;
        }

        $conversation->update([
            'assigned_to' => $assigneeId,
        ]);

        $assigneeName = \App\Models\User::find($assigneeId)?->name ?? 'Unknown';
        $description = "Assigned to {$assigneeName} (auto-assign)";

        $audit = WhatsAppConversationAuditEvent::create([
            'account_id' => $account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'actor_id' => null,
            'event_type' => 'auto_assigned',
            'description' => $description,
            'meta' => [
                'assigned_to' => $assigneeId,
            ],
        ]);

        event(new AuditEventAdded($conversation, [
            'id' => $audit->id,
            'event_type' => $audit->event_type,
            'description' => $audit->description,
            'meta' => $audit->meta,
            'created_at' => $audit->created_at->toIso8601String(),
            'actor' => null,
        ]));

        $this->broadcastSafely(new ConversationUpdated($conversation));
    }
}
