<?php

namespace Tests\Feature;

use App\Models\AccountApiKey;
use App\Models\AccountApiRequestLog;
use App\Models\AccountWebhookDelivery;
use App\Models\AccountWebhookEndpoint;
use App\Models\PlatformSetting;
use App\Services\WorkspaceWebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeveloperTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
        PlatformSetting::set('features.public_api', true, 'boolean', 'features');
        PlatformSetting::set('integrations.api_enabled', true, 'boolean', 'integrations');
        PlatformSetting::set('integrations.api_key', '', 'string', 'integrations');
    }

    public function test_public_api_requests_are_persisted_for_developer_ledger(): void
    {
        $account = $this->createAccountWithPlan('starter');
        [, $token] = AccountApiKey::issue($account, 'Telemetry key', ['connections:read']);

        $this->getJson(route('api.whatsapp.connections'), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertDatabaseHas('account_api_request_logs', [
            'account_id' => $account->id,
            'method' => 'GET',
            'path' => '/api/v1/whatsapp/connections',
            'route_name' => 'api.whatsapp.connections',
            'status' => 200,
            'ip' => '127.0.0.1',
        ]);

        $this->actingAsAccountOwner($account);
        $this->get(route('app.developer.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Developer/Index')
                ->has('requestLogs', 1)
                ->where('requestLogs.0.path', '/api/v1/whatsapp/connections'));

        $this->assertSame(1, AccountApiRequestLog::where('account_id', $account->id)->count());
    }

    public function test_workspace_webhook_deliveries_are_persisted_and_shown(): void
    {
        Http::fake([
            'https://example.com/webhook' => Http::response(['ok' => true], 202),
        ]);

        $account = $this->createAccountWithPlan('starter');
        $endpoint = AccountWebhookEndpoint::create([
            'account_id' => $account->id,
            'url' => 'https://example.com/webhook',
            'secret' => 'secret',
            'events' => ['message.sent'],
            'is_enabled' => true,
        ]);

        app(WorkspaceWebhookDispatcher::class)->dispatch($account, 'message.sent', [
            'message' => ['id' => 123],
        ]);

        $this->assertDatabaseHas('account_webhook_deliveries', [
            'account_id' => $account->id,
            'account_webhook_endpoint_id' => $endpoint->id,
            'event' => 'message.sent',
            'status' => 202,
            'attempts' => 1,
        ]);

        $this->actingAsAccountOwner($account);
        $this->get(route('app.developer.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Developer/Index')
                ->has('webhooks.deliveries', 1)
                ->where('webhooks.deliveries.0.event', 'message.sent')
                ->where('webhooks.deliveries.0.status', 202));

        $this->assertSame(1, AccountWebhookDelivery::where('account_id', $account->id)->count());
    }
}
