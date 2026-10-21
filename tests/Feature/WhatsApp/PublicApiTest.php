<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\AccountUsage;
use App\Models\PlatformSetting;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    protected function enablePublicApi(): void
    {
        PlatformSetting::set('features.public_api', true, 'boolean', 'features');
        PlatformSetting::set('integrations.api_enabled', true, 'boolean', 'integrations');
        PlatformSetting::set('integrations.api_key', 'wacp_test_key', 'string', 'integrations');
    }

    public function test_public_whatsapp_api_requires_valid_key(): void
    {
        $this->enablePublicApi();

        $response = $this->getJson('/api/v1/whatsapp/connections', [
            'X-Account-ID' => '1',
            'X-API-Key' => 'wrong',
        ]);

        $response->assertUnauthorized();
    }

    public function test_public_whatsapp_api_lists_connections_for_account(): void
    {
        $this->enablePublicApi();

        $account = Account::factory()->create();
        WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
            'name' => 'Primary WhatsApp',
        ]);

        $response = $this->getJson('/api/v1/whatsapp/connections', [
            'X-Account-ID' => (string) $account->id,
            'Authorization' => 'Bearer wacp_test_key',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Primary WhatsApp');
    }

    public function test_public_whatsapp_api_accepts_workspace_key_without_account_header(): void
    {
        $this->enablePublicApi();
        PlatformSetting::set('integrations.api_key', '', 'string', 'integrations');

        $account = Account::factory()->create();
        [, $token] = AccountApiKey::issue($account, 'Test key', ['connections:read']);

        WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
            'name' => 'Workspace Key WABA',
        ]);

        $response = $this->getJson('/api/v1/whatsapp/connections', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Workspace Key WABA');

        $this->assertDatabaseHas('account_api_keys', [
            'account_id' => $account->id,
            'last_used_ip' => '127.0.0.1',
        ]);
    }

    public function test_public_whatsapp_api_enforces_workspace_key_scopes(): void
    {
        $this->enablePublicApi();
        PlatformSetting::set('integrations.api_key', '', 'string', 'integrations');

        $account = Account::factory()->create();
        [, $token] = AccountApiKey::issue($account, 'Read-only key', ['connections:read']);

        $response = $this->getJson('/api/v1/whatsapp/templates', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'API key does not include the required scope.');
    }

    public function test_public_whatsapp_send_requires_active_subscription(): void
    {
        $this->enablePublicApi();

        $account = Account::factory()->create();
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $response = $this->postJson('/api/v1/whatsapp/messages/text', [
            'account_id' => $account->id,
            'connection_id' => $connection->id,
            'to' => '919999999999',
            'message' => 'Hello',
        ], [
            'Authorization' => 'Bearer wacp_test_key',
        ]);

        $response->assertStatus(402);
        $this->assertDatabaseCount('whatsapp_contacts', 0);
        $this->assertDatabaseCount('whatsapp_conversations', 0);
    }

    public function test_public_whatsapp_send_tracks_usage(): void
    {
        $this->enablePublicApi();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.public-api-test'],
                ],
            ]),
        ]);

        $account = $this->createAccountWithPlan('self-hosted');
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $response = $this->postJson('/api/v1/whatsapp/messages/text', [
            'account_id' => $account->id,
            'connection_id' => $connection->id,
            'to' => '919999999999',
            'message' => 'Hello',
        ], [
            'Authorization' => 'Bearer wacp_test_key',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.meta_message_id', 'wamid.public-api-test');

        $usage = AccountUsage::where('account_id', $account->id)->firstOrFail();
        $this->assertSame(1, (int) $usage->messages_sent);
    }

    public function test_public_whatsapp_api_sends_location_buttons_and_lists(): void
    {
        $this->enablePublicApi();
        Http::fake([
            'graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.location']]])
                ->push(['messages' => [['id' => 'wamid.buttons']]])
                ->push(['messages' => [['id' => 'wamid.list']]]),
        ]);

        $account = $this->createAccountWithPlan('self-hosted');
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $list = WhatsAppList::create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'name' => 'Help menu',
            'button_text' => 'Choose',
            'description' => 'How can we help?',
            'footer_text' => 'Yoorie',
            'sections' => [[
                'title' => 'Options',
                'rows' => [
                    ['id' => 'support', 'title' => 'Support', 'description' => 'Talk to support'],
                ],
            ]],
            'is_active' => true,
        ]);

        $headers = ['Authorization' => 'Bearer wacp_test_key'];

        $this->postJson('/api/v1/whatsapp/messages/location', [
            'account_id' => $account->id,
            'connection_id' => $connection->id,
            'to' => '919999999999',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'name' => 'Delhi showroom',
        ], $headers)->assertCreated()
            ->assertJsonPath('data.meta_message_id', 'wamid.location');

        $this->postJson('/api/v1/whatsapp/messages/buttons', [
            'account_id' => $account->id,
            'connection_id' => $connection->id,
            'to' => '919999999999',
            'body_text' => 'Choose an option',
            'buttons' => [
                ['id' => 'support', 'text' => 'Support'],
                ['id' => 'sales', 'text' => 'Sales'],
            ],
        ], $headers)->assertCreated()
            ->assertJsonPath('data.meta_message_id', 'wamid.buttons');

        $this->postJson('/api/v1/whatsapp/messages/list', [
            'account_id' => $account->id,
            'connection_id' => $connection->id,
            'to' => '919999999999',
            'list_id' => $list->id,
        ], $headers)->assertCreated()
            ->assertJsonPath('data.meta_message_id', 'wamid.list');

        $usage = AccountUsage::where('account_id', $account->id)->firstOrFail();
        $this->assertSame(3, (int) $usage->messages_sent);

        $this->assertDatabaseHas('whatsapp_messages', [
            'account_id' => $account->id,
            'type' => 'location',
            'status' => 'sent',
        ]);

        $this->assertSame(3, count(Http::recorded()));
    }
}
