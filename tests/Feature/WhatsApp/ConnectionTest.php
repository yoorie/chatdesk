<?php

namespace Tests\Feature\WhatsApp;

use App\Models\Account;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Services\MetaGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);

        $this->account = Account::factory()->create([
            'owner_id' => $this->user->id,
        ]);
        $this->account->users()->attach($this->user->id, ['role' => 'owner']);

        session(['current_account_id' => $this->account->id]);
    }

    public function test_account_member_can_view_connections(): void
    {
        $member = User::factory()->create();
        $this->account->users()->attach($member->id, ['role' => 'admin']);

        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($member)
            ->withSession(['current_account_id' => $this->account->id])
            ->get(route('app.whatsapp.connections.index', ['account' => $this->account->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('WhatsApp/Connections/Index')
            ->has('connections', 1)
        );
    }

    public function test_owner_can_connect_number_manually(): void
    {
        $meta = Mockery::mock(MetaGraphService::class);
        $meta->shouldReceive('getPhoneNumberDetails')
            ->once()
            ->with('1234567890', 'EAAG-permanent-system-user-token')
            ->andReturn(['display_phone_number' => '+91 99999 99999', 'verified_name' => 'Yoorie', 'status' => 'CONNECTED']);
        $this->app->instance(MetaGraphService::class, $meta);

        $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->post(route('app.whatsapp.connections.store'), [
                'phone_number_id' => '1234567890',
                'waba_id' => '9876543210',
                'access_token' => 'EAAG-permanent-system-user-token',
            ])
            ->assertRedirect(route('app.whatsapp.connections.index'));

        $connection = WhatsAppConnection::where('account_id', $this->account->id)->firstOrFail();
        $this->assertSame('manual', $connection->setup_method);
        $this->assertSame('9876543210', $connection->waba_id);
        $this->assertSame('+91 99999 99999', $connection->business_phone);
        $this->assertSame('EAAG-permanent-system-user-token', $connection->access_token);
        $this->assertNotEmpty($connection->webhook_verify_token);
    }

    public function test_manual_connection_validates_ids(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_account_id' => $this->account->id])
            ->post(route('app.whatsapp.connections.store'), [
                'phone_number_id' => 'not-a-number',
                'waba_id' => '',
                'access_token' => 'short',
            ])
            ->assertSessionHasErrors(['phone_number_id', 'waba_id', 'access_token']);

        $this->assertDatabaseCount('whatsapp_connections', 0);
    }

    public function test_member_cannot_create_connection(): void
    {
        $member = User::factory()->create();
        $this->account->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)
            ->withSession(['current_account_id' => $this->account->id])
            ->post(route('app.whatsapp.connections.store', ['account' => $this->account->slug]), [
                'name' => 'Test Connection',
                'phone_number_id' => '123456789',
                'access_token' => 'test-token',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('whatsapp_connections', 0);
    }

    public function test_owner_can_update_connection(): void
    {
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
            'name' => 'Original Name',
        ]);

        $response = $this->actingAs($this->user)
            ->put(route('app.whatsapp.connections.update', [
                'account' => $this->account->slug,
                'connection' => $connection->id,
            ]), [
                'name' => 'Updated Name',
                'phone_number_id' => $connection->phone_number_id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('whatsapp_connections', [
            'id' => $connection->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_owner_can_rotate_verify_token(): void
    {
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $originalToken = $connection->webhook_verify_token;

        $response = $this->actingAs($this->user)
            ->post(route('app.whatsapp.connections.rotate-verify-token', [
                'account' => $this->account->slug,
                'connection' => $connection->id,
            ]));

        $response->assertRedirect();
        $connection->refresh();
        $this->assertNotEquals($originalToken, $connection->webhook_verify_token);
        $this->assertFalse($connection->webhook_subscribed);
    }

    public function test_owner_can_disconnect_connection(): void
    {
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
            'webhook_subscribed' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('app.whatsapp.connections.destroy', [
                'account' => $this->account->slug,
                'connection' => $connection->id,
            ]));

        $response->assertRedirect(route('app.whatsapp.connections.index'));
        $this->assertDatabaseMissing('whatsapp_connections', [
            'id' => $connection->id,
        ]);
    }

    public function test_member_cannot_disconnect_connection(): void
    {
        $member = User::factory()->create();
        $this->account->users()->attach($member->id, ['role' => 'member']);

        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($member)
            ->withSession(['current_account_id' => $this->account->id])
            ->delete(route('app.whatsapp.connections.destroy', [
                'account' => $this->account->slug,
                'connection' => $connection->id,
            ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('whatsapp_connections', [
            'id' => $connection->id,
        ]);
    }

    public function test_disconnect_keeps_waba_subscription_when_other_connections_share_it(): void
    {
        $other = WhatsAppConnection::factory()->create([
            'waba_id' => 'shared-waba',
            'is_active' => true,
        ]);
        $connection = WhatsAppConnection::factory()->create([
            'account_id' => $this->account->id,
            'waba_id' => 'shared-waba',
            'access_token' => 'tok',
            'webhook_subscribed' => true,
        ]);

        $meta = Mockery::mock(MetaGraphService::class);
        $meta->shouldNotReceive('unsubscribeAppFromWaba');
        $this->app->instance(MetaGraphService::class, $meta);

        $this->actingAs($this->user)
            ->delete(route('app.whatsapp.connections.destroy', [
                'account' => $this->account->slug,
                'connection' => $connection->id,
            ]))
            ->assertRedirect(route('app.whatsapp.connections.index'));

        $this->assertDatabaseMissing('whatsapp_connections', ['id' => $connection->id]);
        $this->assertDatabaseHas('whatsapp_connections', ['id' => $other->id]);
    }
}
