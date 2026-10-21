<?php

namespace Tests\Feature;

use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_global_search_returns_workspace_results(): void
    {
        $account = $this->createAccountWithPlan('starter');
        $this->actingAsAccountOwner($account);

        WhatsAppContact::create([
            'account_id' => $account->id,
            'name' => 'Priya Sharma',
            'wa_id' => '919999999999',
            'phone' => '+91 99999 99999',
            'status' => 'active',
        ]);

        $connection = WhatsAppConnection::create([
            'account_id' => $account->id,
            'name' => 'Main WABA',
            'phone_number_id' => '123456789',
            'business_phone' => '+91 90000 00000',
            'access_token_encrypted' => 'test-token',
            'webhook_verify_token' => 'verify-token',
            'is_active' => true,
        ]);

        WhatsAppTemplate::create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'name' => 'diwali_offer',
            'language' => 'en',
            'category' => 'marketing',
            'status' => 'approved',
            'body_text' => 'Diwali offer is live today',
            'components' => [],
        ]);

        Campaign::create([
            'account_id' => $account->id,
            'name' => 'Diwali launch',
            'status' => 'draft',
            'type' => 'template',
            'recipient_type' => 'all',
            'total_recipients' => 0,
        ]);

        $response = $this->getJson(route('app.search', ['q' => 'diwali']));

        $response->assertOk()
            ->assertJsonPath('query', 'diwali')
            ->assertJsonFragment(['type' => 'template', 'label' => 'diwali_offer'])
            ->assertJsonFragment(['type' => 'campaign', 'label' => 'Diwali launch']);
    }

    public function test_global_search_does_not_leak_other_workspace_data(): void
    {
        $account = $this->createAccountWithPlan('starter');
        $other = $this->createAccountWithPlan('starter');
        $this->actingAsAccountOwner($account);

        WhatsAppContact::create([
            'account_id' => $other->id,
            'name' => 'Hidden Customer',
            'wa_id' => '918888888888',
            'status' => 'active',
        ]);

        $response = $this->getJson(route('app.search', ['q' => 'Hidden']));

        $response->assertOk();
        $labels = collect($response->json('results'))->pluck('label');
        $this->assertFalse($labels->contains('Hidden Customer'));
    }
}
