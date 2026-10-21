<?php

namespace App\Jobs;

use App\Mail\PlatformEmailCampaignMail;
use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use App\Services\MailDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendPlatformEmailCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;

    public function __construct(public int $campaignId) {}

    public function handle(MailDeliveryService $mailDelivery): void
    {
        $campaign = PlatformEmailCampaign::query()->find($this->campaignId);
        if (! $campaign || in_array($campaign->status, ['sent', 'failed'], true)) {
            return;
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => $campaign->started_at ?: now(),
            'failure_reason' => null,
        ]);

        $recipients = PlatformEmailCampaignRecipient::query()
            ->where('platform_email_campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($recipients->isEmpty()) {
            $this->finishCampaign($campaign);

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                $outbox = $mailDelivery->sendMailable(
                    $recipient->email,
                    new PlatformEmailCampaignMail($campaign, $recipient),
                    account: $recipient->account,
                    meta: [
                        'platform_email_campaign_id' => $campaign->id,
                        'platform_email_campaign_recipient_id' => $recipient->id,
                    ]
                );

                $recipient->update([
                    'status' => 'sent',
                    'attempts' => DB::raw('attempts + 1'),
                    'sent_at' => now(),
                    'failed_at' => null,
                    'failure_reason' => null,
                    'notification_outbox_id' => $outbox->id,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('Platform email campaign recipient failed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email,
                    'error' => $exception->getMessage(),
                ]);

                $recipient->update([
                    'status' => 'failed',
                    'attempts' => DB::raw('attempts + 1'),
                    'failed_at' => now(),
                    'failure_reason' => substr($exception->getMessage(), 0, 2000),
                ]);
            }
        }

        $campaign->update([
            'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $campaign->recipients()->where('status', 'failed')->count(),
        ]);

        if ($campaign->recipients()->where('status', 'pending')->exists()) {
            self::dispatch($campaign->id)->onQueue('default')->delay(now()->addSeconds(3));

            return;
        }

        $this->finishCampaign($campaign->fresh());
    }

    private function finishCampaign(PlatformEmailCampaign $campaign): void
    {
        $sent = $campaign->recipients()->where('status', 'sent')->count();
        $failed = $campaign->recipients()->where('status', 'failed')->count();

        $campaign->update([
            'status' => $sent > 0 ? 'sent' : 'failed',
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
            'failed_at' => $sent > 0 ? null : now(),
            'failure_reason' => $sent > 0 ? null : 'No recipients were sent successfully.',
        ]);
    }
}
