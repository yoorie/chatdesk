<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConversationAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_to_and_priority_are_mass_assignable(): void
    {
        if (! Schema::hasColumn('whatsapp_conversations', 'assigned_to') || ! Schema::hasColumn('whatsapp_conversations', 'priority')) {
            $this->markTestSkipped('Conversation assignment columns are not available in this schema.');
        }

        $owner = User::factory()->create();
        $account = Account::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
        ]);

        $contact = WhatsAppContact::factory()->create([
            'account_id' => $account->id,
        ]);

        $conversation = WhatsAppConversation::factory()->create([
            'account_id' => $account->id,
            'whatsapp_connection_id' => $connection->id,
            'whatsapp_contact_id' => $contact->id,
            'assigned_to' => null,
            'priority' => 'normal',
        ]);

        $conversation->update([
            'assigned_to' => $owner->id,
            'priority' => 'urgent',
        ]);

        $conversation = $conversation->fresh();

        $this->assertSame($owner->id, (int) $conversation->assigned_to);
        $this->assertSame('urgent', $conversation->priority);
    }
}
