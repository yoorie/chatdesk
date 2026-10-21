<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
    }

    public function test_owner_without_session_workspace_is_not_sent_to_onboarding(): void
    {
        $account = $this->createAccount();
        $owner = User::findOrFail($account->owner_id);

        $this->actingAs($owner)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSessionHas('current_account_id', $account->id);
    }

    public function test_stale_session_workspace_falls_back_to_users_workspace(): void
    {
        $account = $this->createAccount();
        $owner = User::findOrFail($account->owner_id);
        $otherAccount = $this->createAccount();

        // Deleted workspace id and a workspace the user has no access to.
        foreach ([999999, $otherAccount->id] as $staleId) {
            $this->actingAs($owner)
                ->withSession(['current_account_id' => $staleId])
                ->get(route('app.dashboard'))
                ->assertOk()
                ->assertSessionHas('current_account_id', $account->id);
        }
    }

    public function test_member_workspace_is_restored_and_active_workspace_preferred(): void
    {
        $suspended = $this->createAccount();
        $suspended->update(['status' => 'suspended']);
        $active = $this->createAccount();
        $member = User::factory()->create();
        $suspended->users()->attach($member->id, ['role' => 'admin']);
        $active->users()->attach($member->id, ['role' => 'admin']);

        $this->actingAs($member)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSessionHas('current_account_id', $active->id);
    }
}
