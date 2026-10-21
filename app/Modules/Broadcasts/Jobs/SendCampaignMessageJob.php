<?php

namespace App\Modules\Broadcasts\Jobs;

use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Broadcasts\Models\CampaignRecipient;
use App\Modules\Broadcasts\Services\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 60;

    public $timeout = 120;

    protected int $batchSize = 20;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $campaignId
    ) {
        // Use a dedicated queue for campaign messages to avoid conflicts
        $this->onQueue('campaigns');
    }

    /**
     * Execute the job.
     */
    public function handle(CampaignService $campaignService): void
    {
        // Use lock to prevent concurrent processing of the same campaign
        $lockKey = "campaign_send:{$this->campaignId}";
        $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 120); // lock while a batch is processed

        if (! $lock->get()) {
            // Another job is processing this campaign, retry later
            $this->release(10); // Release back to queue for 10 seconds

            return;
        }

        try {
            $campaign = Campaign::find($this->campaignId);

            if (! $campaign || ! $campaign->isActive()) {
                Log::info('Campaign not found or not active', ['campaign_id' => $this->campaignId]);

                return;
            }

            $iterations = $campaign->send_delay_seconds > 0 ? 1 : $this->batchSize;
            $processed = 0;

            for ($i = 0; $i < $iterations; $i++) {
                $recipient = \DB::transaction(function () use ($campaign) {
                    /** @var CampaignRecipient|null $next */
                    $next = $campaign->recipients()
                        ->readyToSend()
                        ->lockForUpdate()
                        ->orderBy('id')
                        ->first();

                    if ($next) {
                        $next->update([
                            'status' => 'sending',
                            'failure_reason' => null,
                        ]);
                    }

                    return $next;
                });

                if (! $recipient) {
                    break;
                }

                $processed++;
                $sent = $campaignService->sendToRecipient($campaign->fresh() ?? $campaign, $recipient->fresh() ?? $recipient);
                if (! $sent && $campaignService->isConnectionCoolingDown($campaign)) {
                    break;
                }
            }

            $this->scheduleRemainder($campaign->fresh() ?? $campaign, $campaignService, $processed);
        } finally {
            $lock->release();
        }
    }

    /**
     * Keep the campaign sending while a 131049 hold is still in the future.
     * Those recipients stay pending but are not eligible until retry_after.
     */
    protected function scheduleRemainder(Campaign $campaign, CampaignService $campaignService, int $processed): void
    {
        $eligible = $campaign->recipients()->readyToSend()->exists();
        $nextHold = $campaign->recipients()
            ->where('status', 'pending')
            ->whereNotNull('retry_after')
            ->where('retry_after', '>', now())
            ->min('retry_after');

        if ($processed === 0 && ! $eligible && $nextHold === null) {
            $campaignService->checkCampaignCompletion($campaign);

            return;
        }

        if (! $eligible && $nextHold === null) {
            $campaignService->checkCampaignCompletion($campaign);

            return;
        }

        $next = (new SendCampaignMessageJob($this->campaignId))->onQueue('campaigns');
        $delaySeconds = $eligible
            ? $campaignService->getDispatchDelaySeconds($campaign)
            : max(1, (int) ceil(now()->diffInSeconds(Carbon::parse($nextHold))));

        if ($delaySeconds > 0) {
            $next->delay(now()->addSeconds($delaySeconds));
        }
        dispatch($next);
    }
}
