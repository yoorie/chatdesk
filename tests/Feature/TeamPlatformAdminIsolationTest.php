<?php

namespace Tests\Feature;

use App\Models\AccountUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPlatformAdminIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_workspace_team_page_hides_platform_admin_memberships(): void
    {
        $account = $this->createAccountWithPlan('starter');
        $owner = $this->actingAsAccountOwner($account);
        $agent = User::factory()->create(['is_platform_admin' => false]);
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $agent->id,
            'role' => 'member',
        ]);
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $platformAdmin->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('app.team.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('App/Team/Index')
            ->where('members.0.email', $owner->email)
            ->has('members', 2)
            ->where('members.1.email', $agent->email));
    }

    public function test_workspace_assignment_agents_exclude_platform_admins(): void
    {
        $account = $this->createAccountWithPlan('starter');
        $agent = User::factory()->create(['is_platform_admin' => false]);
        $platformAdmin = User::factory()->create(['is_platform_admin' => true]);

        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $agent->id,
            'role' => 'member',
        ]);
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $platformAdmin->id,
            'role' => 'admin',
        ]);

        $agents = $account->fresh()->getAssignableAgents();

        $this->assertTrue($agents->pluck('id')->contains($account->owner_id));
        $this->assertTrue($agents->pluck('id')->contains($agent->id));
        $this->assertFalse($agents->pluck('id')->contains($platformAdmin->id));
    }
}
