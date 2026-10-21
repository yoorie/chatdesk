<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected WhatsAppConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'owner_id' => $this->user->id,
        ]);
        $this->account->users()->attach($this->user->id, ['role' => 'owner']);
        app(\App\Core\Billing\SubscriptionService::class)->changePlan(
            $this->account,
            Plan::where('key', 'starter')->firstOrFail(),
            $this->user
        );

        $this->connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
            'waba_id' => '123456789',
        ]);

        session(['current_account_id' => $this->account->id]);
    }

    public function test_owner_can_sync_templates(): void
    {
        // Mock Meta API response
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [
                    [
                        'id' => 'meta_template_123',
                        'name' => 'welcome_message',
                        'language' => 'en_US',
                        'status' => 'APPROVED',
                        'category' => 'UTILITY',
                        'components' => [
                            [
                                'type' => 'BODY',
                                'text' => 'Hello {{1}}, welcome to {{2}}!',
                            ],
                            [
                                'type' => 'FOOTER',
                                'text' => 'Thank you',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('app.whatsapp.templates.sync', ['account' => $this->account->slug]), [
                'connection_id' => $this->connection->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('whatsapp_templates', [
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'name' => 'welcome_message',
            'language' => 'en_US',
            'status' => 'approved',
        ]);
    }

    public function test_sync_is_idempotent(): void
    {
        // Mock Meta API response
        $mockResponse = [
            'data' => [
                [
                    'id' => 'meta_template_123',
                    'name' => 'welcome_message',
                    'language' => 'en_US',
                    'status' => 'APPROVED',
                    'category' => 'UTILITY',
                    'components' => [
                        [
                            'type' => 'BODY',
                            'text' => 'Hello {{1}}!',
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'graph.facebook.com/*' => Http::response($mockResponse, 200),
        ]);

        // First sync
        $this->actingAs($this->user)
            ->post(route('app.whatsapp.templates.sync', ['account' => $this->account->slug]), [
                'connection_id' => $this->connection->id,
            ]);

        $firstCount = WhatsAppTemplate::count();

        // Second sync (should update, not create duplicate)
        $this->actingAs($this->user)
            ->post(route('app.whatsapp.templates.sync', ['account' => $this->account->slug]), [
                'connection_id' => $this->connection->id,
            ]);

        $secondCount = WhatsAppTemplate::count();

        $this->assertEquals($firstCount, $secondCount, 'Template count should remain the same after second sync');
    }

    public function test_template_list_shows_filters(): void
    {
        WhatsAppTemplate::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'name' => 'test_template',
            'status' => 'approved',
            'category' => 'MARKETING',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('app.whatsapp.templates.index', [
                'account' => $this->account->slug,
                'status' => 'approved',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('WhatsApp/Templates/Index')
            ->has('templates.data', 1)
        );
    }

    public function test_sending_template_creates_outbound_message(): void
    {
        $template = WhatsAppTemplate::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'name' => 'test_template',
            'language' => 'en_US',
            'body_text' => 'Hello {{1}}!',
            'status' => 'approved',
        ]);

        // Mock WhatsApp API response
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [
                    ['id' => 'wamid.test123'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('app.whatsapp.templates.send.store', [
                'account' => $this->account->slug,
                'template' => $template->id,
            ]), [
                'to_wa_id' => '1234567890',
                'variables' => ['World'],
            ]);

        $this->assertDatabaseHas('whatsapp_messages', [
            'account_id' => $this->account->id,
            'direction' => 'outbound',
            'type' => 'template',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('whatsapp_template_sends', [
            'account_id' => $this->account->id,
            'whatsapp_template_id' => $template->id,
            'to_wa_id' => '1234567890',
            'status' => 'sent',
        ]);
    }

    public function test_member_cannot_sync_templates(): void
    {
        $member = User::factory()->create();
        $this->account->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)
            ->withSession(['current_account_id' => $this->account->id])
            ->post(route('app.whatsapp.templates.sync', ['account' => $this->account->slug]), [
                'connection_id' => $this->connection->id,
            ]);

        $response->assertStatus(302);
    }
}
