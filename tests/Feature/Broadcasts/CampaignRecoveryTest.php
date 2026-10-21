<?php

namespace Tests\Feature\Broadcasts;

use App\Modules\Broadcasts\Jobs\SendCampaignMessageJob;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Broadcasts\Models\CampaignMessage;
use App\Modules\Broadcasts\Models\CampaignRecipient;
use App\Modules\Broadcasts\Services\CampaignService;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_recovery_starts_due_scheduled_campaigns(): void
    {
        Queue::fake();

        $account = $this->createAccountWithPlan('pro');
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
            'webhook_last_error' => null,
            'webhook_last_received_at' => now(),
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'name' => 'Due recovery campaign',
            'status' => 'scheduled',
            'type' => 'text',
            'message_text' => 'Recovery test',
            'scheduled_at' => now()->subMinute(),
            'recipient_type' => 'custom',
            'custom_recipients' => [['phone' => '919999999999']],
            'total_recipients' => 1,
        ]);

        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '919999999999',
            'name' => 'Recovery Recipient',
            'status' => 'pending',
        ]);

        $result = app(CampaignService::class)->recoverStalledCampaigns();

        $this->assertSame([$campaign->id], $result['started_scheduled']);
        $this->assertSame('sending', $campaign->fresh()->status);
        Queue::assertPushed(SendCampaignMessageJob::class, fn (SendCampaignMessageJob $job) => $job->campaignId === $campaign->id);
    }

    public function test_recovery_requeues_stalled_sending_campaigns(): void
    {
        Queue::fake();

        $account = $this->createAccountWithPlan('pro');
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'name' => 'Stalled sending campaign',
            'status' => 'sending',
            'type' => 'text',
            'message_text' => 'Recovery test',
            'recipient_type' => 'custom',
            'custom_recipients' => [['phone' => '919999999998']],
            'total_recipients' => 1,
            'started_at' => now()->subMinutes(10),
        ]);
        $campaign->forceFill(['updated_at' => now()->subMinutes(10)])->save();

        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '919999999998',
            'name' => 'Pending Recipient',
            'status' => 'pending',
        ]);

        $result = app(CampaignService::class)->recoverStalledCampaigns();

        $this->assertSame([$campaign->id], $result['requeued_sending']);
        $this->assertNotEmpty($campaign->fresh()->metadata['last_requeued_at'] ?? null);
        Queue::assertPushed(SendCampaignMessageJob::class, fn (SendCampaignMessageJob $job) => $job->campaignId === $campaign->id);
    }

    public function test_campaign_webhook_status_updates_only_matching_recipient(): void
    {
        $account = $this->createAccountWithPlan('pro');
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'name' => 'Status isolation campaign',
            'status' => 'sending',
            'type' => 'text',
            'message_text' => 'Status test',
            'recipient_type' => 'custom',
            'custom_recipients' => [
                ['phone' => '919999999991'],
                ['phone' => '919999999992'],
            ],
            'total_recipients' => 2,
            'started_at' => now(),
        ]);

        $first = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '919999999991',
            'status' => 'sent',
            'sent_at' => now(),
            'wamid' => 'wamid.one',
            'message_id' => 'wamid.one',
        ]);
        $second = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '919999999992',
            'status' => 'pending',
        ]);
        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $first->id,
            'wamid' => 'wamid.one',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        app(CampaignService::class)->updateMessageStatus('wamid.one', 'read', now());

        $this->assertSame('read', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, (int) $campaign->fresh()->read_count);
    }

    public function test_campaign_status_progression_clears_stale_failure_fields(): void
    {
        $account = $this->createAccountWithPlan('pro');
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'name' => 'Recovered status campaign',
            'status' => 'sending',
            'type' => 'text',
            'message_text' => 'Status recovery test',
            'recipient_type' => 'custom',
            'custom_recipients' => [['phone' => '919999999991']],
            'total_recipients' => 1,
            'started_at' => now(),
        ]);

        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone_number' => '919999999991',
            'status' => 'failed',
            'failed_at' => now()->subMinute(),
            'failure_reason' => 'Transient provider failure',
            'wamid' => 'wamid.recovered',
            'message_id' => 'wamid.recovered',
        ]);

        CampaignMessage::create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'wamid' => 'wamid.recovered',
            'status' => 'failed',
            'failed_at' => now()->subMinute(),
            'error_message' => 'Transient provider failure',
        ]);

        app(CampaignService::class)->updateMessageStatus('wamid.recovered', 'read', now());

        $recipient->refresh();
        $this->assertSame('read', $recipient->status);
        $this->assertNull($recipient->failed_at);
        $this->assertNull($recipient->failure_reason);
        $this->assertSame(1, (int) $campaign->fresh()->read_count);
        $this->assertSame(0, (int) $campaign->fresh()->failed_count);
    }
}
