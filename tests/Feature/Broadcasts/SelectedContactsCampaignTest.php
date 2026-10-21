<?php

namespace Tests\Feature\Broadcasts;

use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SelectedContactsCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_campaign_form_preselects_contacts_from_contacts_bulk_action(): void
    {
        $account = $this->createAccountWithPlan('self-hosted');
        $this->actingAsAccountOwner($account);
        $picked = WhatsAppContact::factory()->count(2)->create(['account_id' => $account->id]);
        WhatsAppContact::factory()->create(['account_id' => $account->id]);
        $foreign = WhatsAppContact::factory()->create();

        $ids = $picked->pluck('id')->push($foreign->id)->implode(',');

        $this->get(route('app.broadcasts.index', ['panel' => 'create', 'contacts' => $ids]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Broadcasts/Index')
                ->has('createOptions.selectedContacts', 2)
            );
    }

    public function test_selected_contacts_campaign_only_sends_to_those_contacts(): void
    {
        Queue::fake();

        $account = $this->createAccountWithPlan('self-hosted');
        $this->actingAsAccountOwner($account);
        $connection = WhatsAppConnection::factory()->create(['account_id' => $account->id]);
        $picked = WhatsAppContact::factory()->count(2)->create(['account_id' => $account->id]);
        $other = WhatsAppContact::factory()->count(3)->create(['account_id' => $account->id]);
        $foreign = WhatsAppContact::factory()->create();

        $this->post(route('app.broadcasts.store'), [
            'name' => 'Picked people only',
            'type' => 'text',
            'message_text' => 'Hello there',
            'whatsapp_connection_id' => $connection->id,
            'recipient_type' => 'contacts',
            'recipient_filters' => ['contact_ids' => [...$picked->pluck('id')->all(), $foreign->id]],
            'respect_opt_out' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $campaign = Campaign::where('name', 'Picked people only')->firstOrFail();
        $recipientContactIds = $campaign->recipients()->pluck('whatsapp_contact_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($picked->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(), $recipientContactIds);
        $this->assertSame(2, (int) $campaign->total_recipients);
        $this->assertNotContains($foreign->id, $recipientContactIds);
        foreach ($other as $contact) {
            $this->assertNotContains($contact->id, $recipientContactIds);
        }
    }

    public function test_selected_contacts_from_another_workspace_are_rejected(): void
    {
        $account = $this->createAccountWithPlan('self-hosted');
        $this->actingAsAccountOwner($account);
        $connection = WhatsAppConnection::factory()->create(['account_id' => $account->id]);
        WhatsAppContact::factory()->count(2)->create(['account_id' => $account->id]);
        $foreign = WhatsAppContact::factory()->create();

        $this->post(route('app.broadcasts.store'), [
            'name' => 'Foreign only',
            'type' => 'text',
            'message_text' => 'Hello there',
            'whatsapp_connection_id' => $connection->id,
            'recipient_type' => 'contacts',
            'recipient_filters' => ['contact_ids' => [$foreign->id]],
        ])->assertSessionHasErrors('recipient_filters');

        $this->assertDatabaseMissing('campaigns', ['name' => 'Foreign only']);
    }
}
