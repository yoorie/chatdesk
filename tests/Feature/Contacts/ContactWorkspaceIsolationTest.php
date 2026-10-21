<?php

namespace Tests\Feature\Contacts;

use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\Plan;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactWorkspaceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_route_binding_does_not_cross_switch_workspaces(): void
    {
        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);

        $user = User::factory()->create();
        $firstAccount = Account::factory()->create(['owner_id' => $user->id, 'name' => 'First workspace']);
        $secondAccount = Account::factory()->create(['owner_id' => $user->id, 'name' => 'Second workspace']);
        $firstAccount->users()->attach($user->id, ['role' => 'owner']);
        $secondAccount->users()->attach($user->id, ['role' => 'owner']);

        $plan = Plan::where('key', 'starter')->firstOrFail();
        app(SubscriptionService::class)->changePlan($firstAccount, $plan, $user);
        app(SubscriptionService::class)->changePlan($secondAccount, $plan, $user);

        $firstContact = WhatsAppContact::create([
            'account_id' => $firstAccount->id,
            'wa_id' => '919999999001',
            'name' => 'First Contact',
            'phone' => '919999999001',
            'status' => 'active',
        ]);
        $secondContact = WhatsAppContact::create([
            'account_id' => $secondAccount->id,
            'wa_id' => '919999999002',
            'name' => 'Second Contact',
            'phone' => '919999999002',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $firstAccount->id])
            ->put(route('app.contacts.update', $secondContact), [
                'name' => 'Should Not Update',
                'status' => 'active',
            ]);

        $response->assertNotFound();
        $this->assertSame('Second Contact', $secondContact->refresh()->name);
        $this->assertSame($firstAccount->id, session('current_account_id'));

        $response = $this->actingAs($user)
            ->withSession(['current_account_id' => $firstAccount->id])
            ->put(route('app.contacts.update', $firstContact), [
                'name' => 'Updated First Contact',
                'status' => 'active',
            ]);

        $response->assertRedirect();
        $this->assertSame('Updated First Contact', $firstContact->refresh()->name);
    }
}
