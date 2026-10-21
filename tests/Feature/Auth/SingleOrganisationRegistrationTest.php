<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SingleOrganisationRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_first_user_becomes_admin_and_creates_the_organisation(): void
    {
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'Str0ng!Passw0rd#2026',
            'password_confirmation' => 'Str0ng!Passw0rd#2026',
            'terms' => true,
        ])->assertRedirect(route('app.dashboard'));

        $this->assertAuthenticated();
        $this->assertSame(1, Account::count());
        $this->assertSame(User::first()->id, Account::first()->owner_id);
        $this->assertFalse((bool) User::first()->is_platform_admin);
    }

    public function test_registration_is_closed_after_the_first_user(): void
    {
        User::factory()->create();

        $this->get('/register')->assertRedirect(route('login'));
        $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'Str0ng!Passw0rd#2026',
            'password_confirmation' => 'Str0ng!Passw0rd#2026',
            'terms' => true,
        ])->assertForbidden();

        $this->assertSame(0, Account::count());
    }

    public function test_invited_agent_can_join_the_organisation(): void
    {
        $account = $this->createAccountWithPlan('self-hosted');
        $invitation = AccountInvitation::create([
            'account_id' => $account->id,
            'invited_by' => $account->owner_id,
            'email' => 'agent@example.com',
            'role' => 'member',
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
        ]);

        $this->get('/register?invite='.$invitation->token)->assertOk();
        $this->post('/register', [
            'name' => 'Agent',
            'email' => 'agent@example.com',
            'password' => 'Str0ng!Passw0rd#2026',
            'password_confirmation' => 'Str0ng!Passw0rd#2026',
            'invite_token' => $invitation->token,
            'terms' => true,
        ])->assertRedirect(route('app.dashboard'));

        $agent = User::where('email', 'agent@example.com')->firstOrFail();
        $this->assertTrue($account->users()->where('user_id', $agent->id)->exists());
        $this->assertSame(1, Account::count());
    }
}
