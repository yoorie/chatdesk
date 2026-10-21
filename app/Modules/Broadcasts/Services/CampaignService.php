<?php

namespace App\Modules\Broadcasts\Services;

use App\Models\PlatformSetting;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Broadcasts\Models\CampaignMessage;
use App\Modules\Broadcasts\Models\CampaignRecipient;
use App\Modules\Contacts\Models\ContactSegment;
use App\Modules\WhatsApp\Exceptions\WhatsAppApiException;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Services\TemplateComposer;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use App\Services\OperationalAlertService;
use App\Services\UsageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(
        protected WhatsAppClient $whatsappClient,
        protected TemplateComposer $templateComposer,
        protected UsageService $usageService,
        protected OperationalAlertService $alertService
    ) {}

    /**
     * Prepare recipients for a campaign.
     * Uses lock to prevent concurrent preparation.
     */
    public function prepareRecipients(Campaign $campaign): int
    {
        // Use lock to prevent concurrent recipient preparation
        $lockKey = "campaign_prepare_recipients:{$campaign->id}";
        $lock = Cache::lock($lockKey, 300); // 5 minute lock

        if (! $lock->get()) {
            throw new \Exception('Recipient preparation is already in progress for this campaign.');
        }

        try {
            return $this->performPrepareRecipients($campaign);
        } finally {
            $lock->release();
        }
    }

    /**
     * Perform the actual recipient preparation.
     */
    protected array $preparedPhones = [];

    protected function performPrepareRecipients(Campaign $campaign): int
    {
        // Delete existing recipients if re-preparing.
        $campaign->recipients()->delete();
        $this->preparedPhones = [];

        $sampleSize = max(0, (int) Arr::get($campaign->metadata, 'recipient_sample_size', 0));
        $isDryRun = (bool) Arr::get($campaign->metadata, 'dry_run', false);

        if ($campaign->recipient_type === 'custom' || $sampleSize > 0) {
            $recipients = match ($campaign->recipient_type) {
                'contacts' => $this->getRecipientsFromContacts($campaign, $sampleSize),
                'segment' => $this->getRecipientsFromSegment($campaign, $sampleSize),
                'custom' => $this->getCustomRecipients($campaign),
                default => [],
            };

            if ($sampleSize > 0 && count($recipients) > $sampleSize) {
                shuffle($recipients);
                $recipients = array_slice($recipients, 0, $sampleSize);
            }

            $count = $this->insertRecipientRows($campaign, $recipients);
        } else {
            $count = match ($campaign->recipient_type) {
                'contacts' => $this->insertRecipientsFromContacts($campaign),
                'segment' => $this->insertRecipientsFromSegments($campaign),
                default => 0,
            };
        }

        DB::transaction(function () use ($campaign, $count, $isDryRun) {
            $campaign->update([
                'total_recipients' => $count,
                'sent_count' => 0,
                'delivered_count' => 0,
                'read_count' => 0,
                'failed_count' => 0,
                'completed_at' => null,
                'metadata' => array_merge($campaign->metadata ?? [], [
                    'effective_recipient_count' => $count,
                    'dry_run' => $isDryRun,
                ]),
            ]);
        });

        return $count;
    }

    /**
     * Get recipients from contacts.
     */
    protected function getRecipientsFromContacts(Campaign $campaign, int $limit = 0): array
    {
        $query = $this->contactsRecipientQuery($campaign);

        if ($limit > 0) {
            $query->inRandomOrder()->limit($limit);
        }

        return $query->get(['id', 'wa_id', 'name'])->map(function ($contact) {
            return [
                'contact_id' => $contact->id,
                'phone_number' => $contact->wa_id,
                'name' => $contact->name];
        })->toArray();
    }

    protected function contactsRecipientQuery(Campaign $campaign)
    {
        $query = WhatsAppContact::where('account_id', $campaign->account_id)
            ->whereNotNull('wa_id')
            ->where('wa_id', '!=', '');

        if ($campaign->respect_opt_out) {
            $query->whereNotIn('status', ['opt_out', 'blocked']);
        }

        if ($campaign->recipient_filters) {
            $filters = $campaign->recipient_filters;

            // "Selected contacts" audience: only these contacts, never the whole list.
            if (is_array($filters) && array_key_exists('contact_ids', $filters)) {
                $ids = array_values(array_filter(
                    array_map('intval', (array) ($filters['contact_ids'] ?? [])),
                    fn (int $id) => $id > 0
                ));
                $query->whereIn('id', $ids === [] ? [0] : $ids);
            }

            if (isset($filters['has_conversation']) && $filters['has_conversation']) {
                $query->whereHas('conversations');
            }

            if (isset($filters['last_seen_days'])) {
                $days = (int) $filters['last_seen_days'];
                $query->where('last_seen_at', '>=', now()->subDays($days));
            }

            // Allow status filter override
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        }

        return $query;
    }

    protected function insertRecipientsFromContacts(Campaign $campaign): int
    {
        $count = 0;

        $this->contactsRecipientQuery($campaign)
            ->orderBy('id')
            ->chunkById(1000, function ($contacts) use ($campaign, &$count) {
                $rows = $contacts->map(fn ($contact) => [
                    'contact_id' => $contact->id,
                    'phone_number' => $contact->wa_id,
                    'name' => $contact->name,
                ])->all();

                $count += $this->insertRecipientRows($campaign, $rows);
            }, 'id');

        return $count;
    }

    /**
     * Get custom recipients.
     */
    protected function getCustomRecipients(Campaign $campaign): array
    {
        if (! $campaign->custom_recipients) {
            return [];
        }

        return collect($campaign->custom_recipients)
            ->map(function ($recipient) {
                return [
                    'phone_number' => trim((string) ($recipient['phone'] ?? $recipient)),
                    'name' => $recipient['name'] ?? null,
                    'template_params' => $recipient['params'] ?? null,
                ];
            })
            ->filter(fn ($recipient) => $recipient['phone_number'] !== '')
            ->unique(fn ($recipient) => preg_replace('/\D+/', '', $recipient['phone_number']) ?: $recipient['phone_number'])
            ->values()
            ->all();
    }

    /**
     * Get recipients from selected contact segments.
     */
    protected function getRecipientsFromSegment(Campaign $campaign, int $limit = 0): array
    {
        $filters = $campaign->recipient_filters ?? [];
        $segmentIds = [];

        if (isset($filters['segment_id'])) {
            $segmentIds[] = (int) $filters['segment_id'];
        }
        if (isset($filters['segment_ids']) && is_array($filters['segment_ids'])) {
            $segmentIds = array_merge($segmentIds, array_map('intval', $filters['segment_ids']));
        }
        if (isset($filters['segments']) && is_array($filters['segments'])) {
            $segmentIds = array_merge($segmentIds, array_map('intval', $filters['segments']));
        }

        $segmentIds = array_values(array_unique(array_filter($segmentIds)));

        if (empty($segmentIds)) {
            Log::warning('Campaign segment recipients requested without segment IDs', [
                'campaign_id' => $campaign->id,
                'account_id' => $campaign->account_id,
            ]);

            return [];
        }

        $segments = ContactSegment::where('account_id', $campaign->account_id)
            ->whereIn('id', $segmentIds)
            ->get();

        if ($segments->isEmpty()) {
            Log::warning('No segments found for campaign', [
                'campaign_id' => $campaign->id,
                'segment_ids' => $segmentIds,
            ]);

            return [];
        }

        $contacts = collect();

        foreach ($segments as $segment) {
            $query = $segment->contactsQuery();

            if ($campaign->respect_opt_out) {
                $query->whereNotIn('status', ['opt_out', 'blocked']);
            }

            $query->whereNotNull('wa_id')->where('wa_id', '!=', '');
            if ($limit > 0) {
                $query->inRandomOrder()->limit($limit);
            }

            $segmentContacts = $query->get(['id', 'wa_id', 'name']);
            $contacts = $contacts->merge($segmentContacts);

            if ($limit > 0 && $contacts->unique('id')->count() >= $limit) {
                break;
            }
        }

        $contacts = $contacts->unique('id');
        if ($limit > 0) {
            $contacts = $contacts->take($limit);
        }

        return $contacts
            ->map(function ($contact) {
                return [
                    'contact_id' => $contact->id,
                    'phone_number' => $contact->wa_id,
                    'name' => $contact->name,
                ];
            })
            ->values()
            ->toArray();
    }

    protected function insertRecipientsFromSegments(Campaign $campaign): int
    {
        $filters = $campaign->recipient_filters ?? [];
        $segmentIds = [];

        if (isset($filters['segment_id'])) {
            $segmentIds[] = (int) $filters['segment_id'];
        }
        if (isset($filters['segment_ids']) && is_array($filters['segment_ids'])) {
            $segmentIds = array_merge($segmentIds, array_map('intval', $filters['segment_ids']));
        }
        if (isset($filters['segments']) && is_array($filters['segments'])) {
            $segmentIds = array_merge($segmentIds, array_map('intval', $filters['segments']));
        }

        $segmentIds = array_values(array_unique(array_filter($segmentIds)));
        if (empty($segmentIds)) {
            return 0;
        }

        $segments = ContactSegment::where('account_id', $campaign->account_id)
            ->whereIn('id', $segmentIds)
            ->get();

        $count = 0;
        $seenContactIds = [];

        foreach ($segments as $segment) {
            $query = $segment->contactsQuery()
                ->whereNotNull('wa_id')
                ->where('wa_id', '!=', '');

            if ($campaign->respect_opt_out) {
                $query->whereNotIn('status', ['opt_out', 'blocked']);
            }

            $query->orderBy('id')->chunkById(1000, function ($contacts) use ($campaign, &$count, &$seenContactIds) {
                $rows = [];

                foreach ($contacts as $contact) {
                    if (isset($seenContactIds[$contact->id])) {
                        continue;
                    }

                    $seenContactIds[$contact->id] = true;
                    $rows[] = [
                        'contact_id' => $contact->id,
                        'phone_number' => $contact->wa_id,
                        'name' => $contact->name,
                    ];
                }

                $count += $this->insertRecipientRows($campaign, $rows);
            }, 'id');
        }

        return $count;
    }

    protected function insertRecipientRows(Campaign $campaign, array $recipients): int
    {
        $now = now();
        $rows = [];
        $count = 0;

        foreach ($recipients as $recipient) {
            $phone = trim((string) ($recipient['phone_number'] ?? ''));
            $digits = preg_replace('/\D+/', '', $phone) ?: $phone;
            if ($phone === '' || isset($this->preparedPhones[$digits])) {
                continue;
            }
            $this->preparedPhones[$digits] = true;

            $rows[] = [
                'campaign_id' => $campaign->id,
                'whatsapp_contact_id' => $recipient['contact_id'] ?? null,
                'phone_number' => $phone,
                'name' => $recipient['name'] ?? null,
                'template_params' => isset($recipient['template_params'])
                    ? json_encode($recipient['template_params'], JSON_UNESCAPED_UNICODE)
                    : null,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 1000) {
                CampaignRecipient::insert($rows);
                $count += count($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            CampaignRecipient::insert($rows);
            $count += count($rows);
        }

        return $count;
    }

    /**
     * Start sending a campaign.
     */
    public function startCampaign(Campaign $campaign): void
    {
        if (! $campaign->canStart()) {
            throw new \Exception('Campaign cannot be started in its current state.');
        }

        $preflight = $this->runPreflightChecks($campaign);
        if (! $preflight['ok']) {
            throw new \RuntimeException('Campaign preflight failed: '.implode(' | ', $preflight['errors']));
        }

        if ((bool) Arr::get($campaign->metadata, 'dry_run', false)) {
            $campaign->recipients()
                ->where('status', 'pending')
                ->update([
                    'status' => 'skipped',
                    'failure_reason' => 'Dry run mode: send skipped.',
                ]);

            $campaign->update([
                'status' => 'completed',
                'started_at' => $campaign->started_at ?? now(),
                'completed_at' => now(),
                'metadata' => array_merge($campaign->metadata ?? [], [
                    'dry_run_completed_at' => now()->toIso8601String(),
                    'dry_run_preflight' => $preflight,
                ]),
            ]);

            return;
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => $campaign->started_at ?? now(),
            'completed_at' => null,
            'metadata' => array_merge($campaign->metadata ?? [], ['last_preflight' => $preflight]),
        ]);

        // Dispatch job to send campaign messages (on dedicated queue)
        $this->dispatchNextSend($campaign->id);
    }

    /**
     * Send message to a single recipient.
     */
    public function sendToRecipient(Campaign $campaign, CampaignRecipient $recipient): bool
    {
        if (! $campaign->connection) {
            Log::error('Campaign has no connection', ['campaign_id' => $campaign->id]);
            $this->markRecipientFailed($recipient, 'No WhatsApp connection configured');

            return false;
        }

        if (! $this->canSendNowForConnection($campaign)) {
            $recipient->update([
                'status' => 'pending',
                'failure_reason' => 'Deferred by throughput/quiet-hours policy.',
            ]);

            return false;
        }

        // Check if contact has opted out or is blocked (if respect_opt_out is enabled).
        // Custom lists often have only a phone number, so match the workspace contact too.
        if ($campaign->respect_opt_out) {
            $contact = $recipient->whatsapp_contact_id
                ? WhatsAppContact::find($recipient->whatsapp_contact_id)
                : null;
            if (! $contact) {
                $digits = preg_replace('/\D+/', '', (string) $recipient->phone_number);
                if ($digits !== '') {
                    $contact = WhatsAppContact::where('account_id', $campaign->account_id)
                        ->where(function ($query) use ($digits) {
                            $query->where('wa_id', $digits)->orWhere('phone', $digits);
                        })
                        ->first();
                }
            }
            if ($contact && in_array($contact->status ?? 'active', ['opt_out', 'blocked'], true)) {
                CampaignRecipient::whereKey($recipient->id)->update([
                    'status' => 'skipped',
                    'failure_reason' => "Contact has {$contact->status} status"]);
                $this->syncCampaignStats((int) $campaign->id);

                return false;
            }
        }

        try {
            $response = null;

            switch ($campaign->type) {
                case 'template':
                    $response = $this->sendTemplateMessage($campaign, $recipient);
                    break;
                case 'text':
                    $response = $this->sendTextMessage($campaign, $recipient);
                    break;
                case 'media':
                    $response = $this->sendMediaMessage($campaign, $recipient);
                    break;
            }

            if ($response && isset($response['messages'][0]['id'])) {
                $wamid = $response['messages'][0]['id'];

                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'wamid' => $wamid,
                    'message_id' => $wamid,
                    'retry_after' => null,
                    'failure_reason' => null,
                ]);

                // Create campaign message record (use updateOrCreate to handle unique constraint)
                CampaignMessage::updateOrCreate(
                    [
                        'campaign_recipient_id' => $recipient->id, // Unique constraint
                    ],
                    [
                        'campaign_id' => $campaign->id,
                        'wamid' => $wamid,
                        'status' => 'sent',
                        'sent_at' => now()]
                );

                if ($campaign->account) {
                    $this->usageService->incrementMessageUsage($campaign->account, 1);
                    if ($campaign->type === 'template') {
                        $this->usageService->incrementTemplateUsage($campaign->account, 1);
                    }
                }
                $this->syncCampaignStats((int) $campaign->id);
                $this->clearConnectionBackoff($campaign);

                return true;
            }

            $this->markRecipientFailed($recipient, 'No message ID in response');

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send campaign message', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage()]);

            if ($this->isEcosystemHold($e)) {
                $this->deferEcosystemHold($campaign, $recipient, $e);

                return false;
            }

            if ($this->isRateLimitError($e->getMessage())) {
                $this->applyConnectionBackoff($campaign, $e->getMessage());
                $recipient->update([
                    'status' => 'pending',
                    'failure_reason' => 'Rate limited by provider. Retrying with adaptive delay.',
                    'retry_after' => null,
                ]);

                return false;
            }

            $this->markRecipientFailed($recipient, $e->getMessage());

            return false;
        }
    }

    /**
     * Send template message.
     */
    protected function sendTemplateMessage(Campaign $campaign, CampaignRecipient $recipient): array
    {
        if (! $campaign->template) {
            throw new \Exception('Template not found for campaign');
        }

        $template = $campaign->template;
        $components = [];

        // Build template components from params
        $components = [];
        $params = array_merge(
            $campaign->template_params ?? [],
            $recipient->template_params ?? []
        );

        if (! empty($params)) {
            // Extract components from prepared payload
            $payload = $this->templateComposer->preparePayload($template, $recipient->phone_number, $params);
            $components = $payload['template']['components'] ?? [];
        }

        return $this->whatsappClient->sendTemplateMessage(
            $campaign->connection,
            $recipient->phone_number,
            $template->name,
            $template->language,
            $components
        );
    }

    /**
     * Send text message.
     */
    protected function sendTextMessage(Campaign $campaign, CampaignRecipient $recipient): array
    {
        if (! $campaign->message_text) {
            throw new \Exception('Message text not provided');
        }

        return $this->whatsappClient->sendTextMessage(
            $campaign->connection,
            $recipient->phone_number,
            $campaign->message_text
        );
    }

    /**
     * Send media message.
     */
    protected function sendMediaMessage(Campaign $campaign, CampaignRecipient $recipient): array
    {
        if (! $campaign->media_url || ! $campaign->media_type) {
            throw new \Exception('Media URL or media type not provided');
        }

        $caption = null;
        if (in_array($campaign->media_type, ['image', 'video', 'document'], true)) {
            $caption = $campaign->message_text ?: null;
        }

        $filename = null;
        if ($campaign->media_type === 'document') {
            $path = parse_url($campaign->media_url, PHP_URL_PATH);
            $filename = $path ? basename($path) : null;
        }

        return $this->whatsappClient->sendMediaMessage(
            $campaign->connection,
            $recipient->phone_number,
            $campaign->media_type,
            $campaign->media_url,
            $caption,
            $filename
        );
    }

    /**
     * Mark recipient as failed.
     */
    protected function markRecipientFailed(CampaignRecipient $recipient, string $reason): void
    {
        $reason = trim($reason) !== '' ? $reason : 'Campaign delivery failed before Meta returned a detailed error.';

        CampaignRecipient::whereKey($recipient->id)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $reason]);

        CampaignMessage::where('campaign_recipient_id', $recipient->id)->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $reason,
        ]);

        $this->syncCampaignStats((int) $recipient->campaign_id);
        $this->checkAndAlertCampaignErrorRate((int) $recipient->campaign_id);
    }

    /**
     * Update message status from webhook.
     * Uses lock to prevent concurrent status updates.
     */
    public function updateMessageStatus(string $wamid, string $status, ?\DateTime $timestamp = null, ?string $failureReason = null): void
    {
        // Use lock to prevent concurrent status updates
        $lockKey = "campaign_status_update:{$wamid}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 30);

        if (! $lock->get()) {
            // Another process is updating this status
            return;
        }

        try {
            $message = CampaignMessage::where('wamid', $wamid)
                ->lockForUpdate() // Row-level lock
                ->first();

            if (! $message) {
                return;
            }

            $currentRank = $this->campaignStatusRank((string) $message->status);
            $incomingRank = $this->campaignStatusRank($status);
            if ($incomingRank < $currentRank && $status !== 'failed') {
                return;
            }

            $updateData = ['status' => $status];
            $recipientUpdate = ['status' => $status];

            if ($status !== 'failed') {
                $updateData['failed_at'] = null;
                $updateData['error_message'] = null;
                $recipientUpdate['failed_at'] = null;
                $recipientUpdate['failure_reason'] = null;
            }

            switch ($status) {
                case 'delivered':
                    $updateData['delivered_at'] = $timestamp ?? now();
                    $recipientUpdate['delivered_at'] = $timestamp ?? now();
                    break;
                case 'read':
                    $updateData['read_at'] = $timestamp ?? now();
                    $recipientUpdate['read_at'] = $timestamp ?? now();
                    break;
                case 'failed':
                    $updateData['failed_at'] = $timestamp ?? now();
                    $recipientUpdate['failed_at'] = $timestamp ?? now();
                    $recipientUpdate['failure_reason'] = $failureReason ?: 'Provider marked delivery failed.';
                    $updateData['error_message'] = $recipientUpdate['failure_reason'];
                    break;
            }

            $message->update($updateData);
            if ($message->campaign_recipient_id) {
                CampaignRecipient::whereKey($message->campaign_recipient_id)->update($recipientUpdate);
            }
            $this->syncCampaignStats((int) $message->campaign_id);
        } finally {
            $lock->release();
        }
    }

    /**
     * Check if campaign is complete.
     */
    public function checkCampaignCompletion(Campaign $campaign): void
    {
        $pendingCount = $campaign->recipients()
            ->whereIn('status', ['pending', 'sending'])
            ->count();

        if ($pendingCount === 0 && $campaign->status === 'sending') {
            $this->syncCampaignStats((int) $campaign->id);
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now()]);
        }
    }

    public function syncCampaignStats(int $campaignId): void
    {
        $stats = CampaignRecipient::query()
            ->where('campaign_id', $campaignId)
            ->selectRaw('count(*) as total_recipients')
            ->selectRaw("sum(case when status in ('sent', 'delivered', 'read') then 1 else 0 end) as sent_count")
            ->selectRaw("sum(case when status in ('delivered', 'read') then 1 else 0 end) as delivered_count")
            ->selectRaw("sum(case when status = 'read' then 1 else 0 end) as read_count")
            ->selectRaw("sum(case when status = 'failed' then 1 else 0 end) as failed_count")
            ->first();

        if (! $stats) {
            return;
        }

        Campaign::whereKey($campaignId)->update([
            'total_recipients' => (int) $stats->total_recipients,
            'sent_count' => (int) $stats->sent_count,
            'delivered_count' => (int) $stats->delivered_count,
            'read_count' => (int) $stats->read_count,
            'failed_count' => (int) $stats->failed_count,
        ]);
    }

    protected function campaignStatusRank(string $status): int
    {
        return match ($status) {
            'sent' => 10,
            'failed' => 15,
            'delivered' => 20,
            'read' => 30,
            default => 0,
        };
    }

    public function retryFailedRecipients(Campaign $campaign): int
    {
        $failedRecipients = $campaign->recipients()
            ->where('status', 'failed')
            ->get();

        $failedCount = $failedRecipients->count();
        if ($failedCount === 0) {
            return 0;
        }

        DB::transaction(function () use ($campaign, $failedRecipients, $failedCount) {
            foreach ($failedRecipients as $recipient) {
                $recipient->update([
                    'status' => 'pending',
                    'failed_at' => null,
                    'failure_reason' => null,
                    'retry_after' => null,
                    'attempt_count' => 0,
                    'sent_at' => null,
                    'delivered_at' => null,
                    'read_at' => null,
                    'message_id' => null,
                    'wamid' => null,
                ]);
            }

            $campaign->update([
                'status' => 'sending',
                'completed_at' => null,
                'failed_count' => max(0, (int) $campaign->failed_count - $failedCount),
            ]);
        });

        $this->dispatchNextSend($campaign->id);

        return $failedCount;
    }

    /**
     * Recover campaigns whose queue job was missed, killed, or never processed.
     *
     * This method is intentionally conservative: it only touches due scheduled
     * campaigns or campaigns already marked as sending with pending recipients.
     * The send job itself uses a campaign lock, so duplicate recovery attempts
     * do not send the same recipient twice.
     */
    public function recoverStalledCampaigns(int $limit = 25): array
    {
        $started = [];
        $requeued = [];
        $completed = [];
        $errors = [];

        $dueScheduled = Campaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->where('total_recipients', '>', 0)
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        foreach ($dueScheduled as $campaign) {
            try {
                $this->startCampaign($campaign);
                $started[] = $campaign->id;
            } catch (\Throwable $e) {
                $errors[] = [
                    'campaign_id' => $campaign->id,
                    'stage' => 'scheduled_start',
                    'error' => $e->getMessage(),
                ];
                Log::warning('Failed to recover due scheduled campaign', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $remainingLimit = max(0, $limit - count($started));
        if ($remainingLimit > 0) {
            $stalledSending = Campaign::query()
                ->where('status', 'sending')
                ->whereHas('recipients', function ($query) {
                    $query->where('status', 'sending')
                        ->orWhere(function ($pending) {
                            $pending->readyToSend();
                        });
                })
                ->where(function ($query) {
                    $query->whereNull('updated_at')
                        ->orWhere('updated_at', '<=', now()->subMinutes(2));
                })
                ->orderBy('updated_at')
                ->limit($remainingLimit)
                ->get();

            foreach ($stalledSending as $campaign) {
                try {
                    $campaign->recipients()
                        ->where('status', 'sending')
                        ->whereNull('wamid')
                        ->update([
                            'status' => 'pending',
                            'failure_reason' => 'Recovered from interrupted campaign worker.',
                        ]);
                    $this->dispatchNextSend($campaign->id);
                    $campaign->forceFill([
                        'metadata' => array_merge($campaign->metadata ?? [], [
                            'last_requeued_at' => now()->toIso8601String(),
                        ]),
                    ])->save();
                    $requeued[] = $campaign->id;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'campaign_id' => $campaign->id,
                        'stage' => 'sending_requeue',
                        'error' => $e->getMessage(),
                    ];
                    Log::warning('Failed to requeue stalled campaign', [
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Campaign::query()
            ->where('status', 'sending')
            ->whereDoesntHave('recipients', fn ($query) => $query->whereIn('status', ['pending', 'sending']))
            ->limit($limit)
            ->get()
            ->each(function (Campaign $campaign) use (&$completed) {
                $this->checkCampaignCompletion($campaign);
                $completed[] = $campaign->id;
            });

        return [
            'started_scheduled' => $started,
            'requeued_sending' => $requeued,
            'completed' => $completed,
            'errors' => $errors,
        ];
    }

    public function duplicateCampaign(Campaign $campaign, int $userId): Campaign
    {
        $copy = Campaign::create([
            'account_id' => $campaign->account_id,
            'whatsapp_connection_id' => $campaign->whatsapp_connection_id,
            'whatsapp_template_id' => $campaign->whatsapp_template_id,
            'created_by' => $userId,
            'name' => $campaign->name.' (Copy)',
            'description' => $campaign->description,
            'type' => $campaign->type,
            'status' => 'draft',
            'template_params' => $campaign->template_params,
            'message_text' => $campaign->message_text,
            'media_url' => $campaign->media_url,
            'media_type' => $campaign->media_type,
            'scheduled_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'recipient_type' => $campaign->recipient_type,
            'recipient_filters' => $campaign->recipient_filters,
            'custom_recipients' => $campaign->custom_recipients,
            'send_delay_seconds' => $campaign->send_delay_seconds,
            'respect_opt_out' => $campaign->respect_opt_out,
            'metadata' => $campaign->metadata,
        ]);

        $this->prepareRecipients($copy);

        return $copy;
    }

    protected function dispatchNextSend(int $campaignId): void
    {
        \App\Modules\Broadcasts\Jobs\SendCampaignMessageJob::dispatch($campaignId)
            ->onQueue('campaigns');
    }

    public function runPreflightChecks(Campaign $campaign): array
    {
        $errors = [];
        $warnings = [];

        $connection = $campaign->connection;
        if (! $connection) {
            $errors[] = 'No WhatsApp connection configured.';
        } else {
            if (! ((bool) $connection->is_active)) {
                $errors[] = 'Selected WhatsApp connection is inactive.';
            }

            if ($connection->webhook_last_error) {
                $warnings[] = 'Connection has recent webhook errors.';
            }

            if ((! $connection->webhook_last_received_at || $connection->webhook_last_received_at->lt(now()->subHours(24)))) {
                $warnings[] = 'Connection webhook activity is stale (>24h).';
            }

            if ($connection->quiet_hours_start && $connection->quiet_hours_end && $this->getQuietHoursDelaySeconds($campaign) > 0) {
                $warnings[] = 'Connection is currently inside quiet-hours window; send will be deferred automatically.';
            }
        }

        if ($campaign->type === 'template') {
            $template = $campaign->template;
            if (! $template) {
                $errors[] = 'Template campaign requires an approved template.';
            } else {

                $status = strtolower(trim((string) $template->status));
                if ($status !== 'approved') {
                    $errors[] = 'Template is not approved.';
                }
                if ((bool) $template->is_archived) {
                    $errors[] = 'Template is archived.';
                }
                if (
                    $campaign->whatsapp_connection_id
                    && $template->whatsapp_connection_id
                    && (int) $template->whatsapp_connection_id !== (int) $campaign->whatsapp_connection_id
                ) {
                    $errors[] = 'Template does not belong to the selected connection.';
                }

            }
        }

        $pendingCount = (int) $campaign->recipients()->where('status', 'pending')->count();
        if ($pendingCount <= 0) {
            $errors[] = 'No pending recipients to send.';
        }

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'pending_recipients' => $pendingCount,
        ];
    }

    public function getDispatchDelaySeconds(Campaign $campaign): int
    {
        $baseDelay = max(0, (int) $campaign->send_delay_seconds);
        $backoffUntil = $this->getConnectionBackoffUntil($campaign);
        $remainingBackoff = $backoffUntil ? max(0, now()->diffInSeconds($backoffUntil, false)) : 0;
        $quietHoursDelay = $this->getQuietHoursDelaySeconds($campaign);

        return max($baseDelay, $remainingBackoff, $quietHoursDelay);
    }

    public function isConnectionCoolingDown(Campaign $campaign): bool
    {
        $until = $this->getConnectionBackoffUntil($campaign);
        if ($until !== null && $until->isFuture()) {
            return true;
        }

        return $this->getQuietHoursDelaySeconds($campaign) > 0;
    }

    protected function isEcosystemHold(\Exception $e): bool
    {
        if (preg_match('/\b131049\b/', $e->getMessage()) === 1) {
            return true;
        }

        if (! $e instanceof WhatsAppApiException) {
            return false;
        }

        $bodyCode = $e->getResponseBody()['error']['code'] ?? null;

        return (int) $bodyCode === 131049 || (int) $e->getCode() === 131049;
    }

    /**
     * Meta 131049 is per recipient. Do not cool down the whole connection,
     * and do not make the recipient eligible again until the hold elapses.
     */
    protected function deferEcosystemHold(Campaign $campaign, CampaignRecipient $recipient, \Exception $e): void
    {
        $max = max(1, (int) config('whatsapp.campaigns.ecosystem_retry_max', 3));
        $delay = max(60, (int) config('whatsapp.campaigns.ecosystem_retry_delay_seconds', 86400));
        $attempts = (int) $recipient->attempt_count + 1;
        $reason = 'Meta error 131049: WhatsApp held this marketing message to keep a healthy experience for the customer.';

        if ($attempts >= $max) {
            CampaignRecipient::whereKey($recipient->id)->update([
                'attempt_count' => $attempts,
                'retry_after' => null,
            ]);
            $this->markRecipientFailed($recipient, $reason.' Retry limit reached (error code 131049).');

            Log::warning('Campaign recipient hit Meta 131049 retry cap', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'attempts' => $attempts,
            ]);

            return;
        }

        $retryAfter = now()->addSeconds($delay);
        CampaignRecipient::whereKey($recipient->id)->update([
            'status' => 'pending',
            'failed_at' => null,
            'attempt_count' => $attempts,
            'retry_after' => $retryAfter,
            'failure_reason' => $reason.' Retry scheduled (error code 131049, attempt '.$attempts.' of '.$max.').',
        ]);

        Log::warning('Campaign recipient deferred for Meta 131049', [
            'campaign_id' => $campaign->id,
            'recipient_id' => $recipient->id,
            'attempts' => $attempts,
            'retry_after' => $retryAfter->toIso8601String(),
            'error' => mb_substr($e->getMessage(), 0, 500),
        ]);
    }

    protected function isRateLimitError(string $message): bool
    {
        if (preg_match('/\b131049\b/', $message) === 1) {
            return false;
        }

        $normalized = strtolower($message);

        return str_contains($normalized, 'rate limit')
            || str_contains($normalized, 'too many requests')
            || str_contains($normalized, 'error code: 4')
            || str_contains($normalized, '429');
    }

    protected function applyConnectionBackoff(Campaign $campaign, string $reason): void
    {
        if (! $campaign->whatsapp_connection_id) {
            return;
        }

        $penaltyKey = "campaign:connection:{$campaign->whatsapp_connection_id}:rate_penalty";
        $untilKey = "campaign:connection:{$campaign->whatsapp_connection_id}:rate_limited_until";

        $penalty = max(1, (int) Cache::increment($penaltyKey));
        Cache::put($penaltyKey, $penalty, now()->addMinutes(20));

        $waitSeconds = min(180, 5 * (2 ** max(0, $penalty - 1)));
        Cache::put($untilKey, now()->addSeconds($waitSeconds)->toIso8601String(), now()->addSeconds($waitSeconds + 120));

        Log::warning('Campaign connection rate-limited; adaptive backoff applied', [
            'campaign_id' => $campaign->id,
            'connection_id' => $campaign->whatsapp_connection_id,
            'wait_seconds' => $waitSeconds,
            'reason' => mb_substr($reason, 0, 500),
        ]);
    }

    protected function clearConnectionBackoff(Campaign $campaign): void
    {
        if (! $campaign->whatsapp_connection_id) {
            return;
        }

        Cache::forget("campaign:connection:{$campaign->whatsapp_connection_id}:rate_penalty");
        Cache::forget("campaign:connection:{$campaign->whatsapp_connection_id}:rate_limited_until");
    }

    protected function getConnectionBackoffUntil(Campaign $campaign): ?Carbon
    {
        if (! $campaign->whatsapp_connection_id) {
            return null;
        }

        $raw = Cache::get("campaign:connection:{$campaign->whatsapp_connection_id}:rate_limited_until");
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function canSendNowForConnection(Campaign $campaign): bool
    {
        if ($this->getQuietHoursDelaySeconds($campaign) > 0) {
            return false;
        }

        $connection = $campaign->connection;
        if (! $connection) {
            return false;
        }

        $cap = (int) ($connection->throughput_cap_per_minute ?: 120);
        $key = sprintf('campaign:throughput:%d:%s', $connection->id, now()->format('YmdHi'));
        $current = (int) Cache::get($key, 0);

        if ($current >= $cap) {
            $secondsToNextMinute = max(1, 60 - (int) now()->second);
            $untilKey = "campaign:connection:{$connection->id}:rate_limited_until";
            Cache::put($untilKey, now()->addSeconds($secondsToNextMinute)->toIso8601String(), now()->addSeconds($secondsToNextMinute + 90));

            return false;
        }

        Cache::put($key, $current + 1, 70);

        return true;
    }

    protected function getQuietHoursDelaySeconds(Campaign $campaign): int
    {
        $connection = $campaign->connection;
        if (! $connection || ! $connection->quiet_hours_start || ! $connection->quiet_hours_end) {
            return 0;
        }

        $tz = $connection->quiet_hours_timezone ?: config('app.timezone', 'UTC');
        try {
            $localNow = now()->setTimezone($tz);
            [$startHour, $startMinute] = array_map('intval', explode(':', (string) $connection->quiet_hours_start));
            [$endHour, $endMinute] = array_map('intval', explode(':', (string) $connection->quiet_hours_end));

            $start = $localNow->copy()->setTime($startHour, $startMinute, 0);
            $end = $localNow->copy()->setTime($endHour, $endMinute, 0);
            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay();
                if ($localNow->lessThan($start)) {
                    $start->subDay();
                }
            }

            if ($localNow->betweenIncluded($start, $end)) {
                return max(1, $localNow->diffInSeconds($end, false));
            }
        } catch (\Throwable) {
            return 0;
        }

        return 0;
    }

    public function sendTestMessage(Campaign $campaign, string $targetWaId): array
    {
        if (! $campaign->connection) {
            throw new \RuntimeException('Campaign has no WhatsApp connection configured.');
        }

        $targetWaId = trim($targetWaId);
        if ($targetWaId === '') {
            throw new \RuntimeException('Target phone is required for test send.');
        }

        return match ($campaign->type) {
            'template' => $this->sendTemplateTestMessage($campaign, $targetWaId),
            'media' => $this->whatsappClient->sendMediaMessage(
                $campaign->connection,
                $targetWaId,
                (string) $campaign->media_type,
                (string) $campaign->media_url,
                $campaign->message_text ?: null,
                null
            ),
            default => $this->whatsappClient->sendTextMessage($campaign->connection, $targetWaId, (string) $campaign->message_text),
        };
    }

    protected function sendTemplateTestMessage(Campaign $campaign, string $targetWaId): array
    {
        if (! $campaign->template) {
            throw new \RuntimeException('Template not found for campaign.');
        }

        $template = $campaign->template;
        $components = [];
        $params = $campaign->template_params ?? [];
        if (! empty($params)) {
            $payload = $this->templateComposer->preparePayload($template, $targetWaId, $params);
            $components = $payload['template']['components'] ?? [];
        }

        return $this->whatsappClient->sendTemplateMessage(
            $campaign->connection,
            $targetWaId,
            $template->name,
            $template->language,
            $components
        );
    }

    protected function checkAndAlertCampaignErrorRate(int $campaignId): void
    {
        $campaign = Campaign::find($campaignId);
        if (! $campaign) {
            return;
        }

        $processed = max(0, (int) $campaign->sent_count + (int) $campaign->failed_count);
        if ($processed < 20) {
            return;
        }

        $failed = max(0, (int) $campaign->failed_count);
        $rate = $processed > 0 ? ($failed / $processed) * 100 : 0;
        $threshold = max(1, (float) PlatformSetting::get('alerts.campaign_error_rate_threshold_pct', 20));

        if ($rate < $threshold) {
            return;
        }

        $this->alertService->send(
            eventKey: 'campaign.error_rate.high',
            title: 'High campaign failure rate detected',
            context: [
                'scope' => 'campaign:'.$campaign->id,
                'campaign_id' => $campaign->id,
                'campaign_name' => $campaign->name,
                'processed' => $processed,
                'failed' => $failed,
                'failure_rate_pct' => round($rate, 2),
                'threshold_pct' => $threshold,
            ],
            severity: 'warning'
        );
    }
}
