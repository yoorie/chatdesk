<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAuditEvent;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WebhookProcessor;
use App\Services\DashboardMessageMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InboxMessageCountsTest extends TestCase
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
        $this->account = Account::factory()->create(['owner_id' => $this->user->id]);
        $this->account->users()->attach($this->user->id, ['role' => 'member']);
        app(\App\Core\Billing\SubscriptionService::class)->changePlan(
            $this->account,
            Plan::where('key', 'starter')->firstOrFail(),
            $this->user
        );
        $this->connection = WhatsAppConnection::factory()->create(['account_id' => $this->account->id]);
        session(['current_account_id' => $this->account->id]);
    }

    protected function inboundHi(string $wamid = 'wamid.hi-1'): void
    {
        Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

        app(WebhookProcessor::class)->process([
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messages' => [[
                            'id' => $wamid,
                            'from' => '919800000001',
                            'type' => 'text',
                            'text' => ['body' => 'Hi'],
                        ]],
                        'contacts' => [['profile' => ['name' => 'Asha']]],
                    ],
                ]],
            ]],
        ], $this->connection);
    }

    public function test_inbound_webhook_message_counts_once_on_the_contact(): void
    {
        $this->inboundHi();
        $this->inboundHi(); // Meta retries the same message: must not double count.

        $contact = WhatsAppContact::where('account_id', $this->account->id)->firstOrFail();
        $this->assertSame(1, (int) $contact->fresh()->message_count);
    }

    public function test_every_stored_message_updates_the_contact_count_except_reactions(): void
    {
        $contact = WhatsAppContact::factory()->create(['account_id' => $this->account->id, 'message_count' => 0]);
        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_connection_id' => $this->connection->id,
            'whatsapp_contact_id' => $contact->id,
        ]);

        foreach (['inbound', 'outbound'] as $direction) {
            WhatsAppMessage::factory()->create([
                'account_id' => $this->account->id,
                'whatsapp_conversation_id' => $conversation->id,
                'direction' => $direction,
            ]);
        }
        WhatsAppMessage::factory()->create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'type' => 'reaction',
        ]);

        $this->assertSame(2, (int) $contact->fresh()->message_count);
    }

    public function test_inbox_page_ships_the_open_thread_and_message_counts(): void
    {
        // Http::fake() below would also answer the SSR renderer; this test only checks props.
        config(['inertia.ssr.enabled' => false]);
        $this->inboundHi();
        $conversation = WhatsAppConversation::where('account_id', $this->account->id)->firstOrFail();
        WhatsAppConversationAuditEvent::create([
            'account_id' => $this->account->id,
            'whatsapp_conversation_id' => $conversation->id,
            'event_type' => 'automation_skipped',
            'description' => 'No auto-reply sent',
            'meta' => ['status_kind' => 'automation'],
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.conversations.index', ['account' => $this->account->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WhatsApp/Conversations/Index')
                ->where('conversations.data.0.message_counts.received', 1)
                ->where('conversations.data.0.message_counts.sent', 0)
                ->where('initial_thread.conversation_id', $conversation->id)
                ->has('initial_thread.messages', 1)
                ->where('initial_thread.messages.0.text_body', 'Hi')
                ->where('initial_thread.messages.0.direction', 'inbound')
                ->has('initial_thread.audit_events', 1)
                ->has('initial_thread.server_time')
            );
    }

    public function test_conversation_stream_reports_message_counts(): void
    {
        $this->inboundHi();
        $conversation = WhatsAppConversation::where('account_id', $this->account->id)->firstOrFail();

        $data = $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.inbox.conversation.stream', [
                'account' => $this->account->slug,
                'conversation' => $conversation->id,
            ]))
            ->assertOk()
            ->json();

        $this->assertCount(1, $data['new_messages']);
        $this->assertSame([], $data['updated_messages']);
        $this->assertSame(['received' => 1, 'sent' => 0, 'total' => 1], $data['conversation']['message_counts']);
    }

    public function test_dashboard_metrics_split_sent_and_received(): void
    {
        Cache::flush();
        $this->inboundHi();

        $metrics = app(DashboardMessageMetrics::class)->forAccount($this->account->id);

        $this->assertSame(1, $metrics['today']);
        $this->assertSame(1, $metrics['this_month']);
        $this->assertSame(0, $metrics['outbound_this_month']);
        $this->assertSame(0, $metrics['outbound_today']);
        $this->assertSame(1, $metrics['inbound_today']);
        $this->assertSame(1, $metrics['inbound_this_month']);
    }
}
