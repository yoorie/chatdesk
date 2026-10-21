<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_short_urls_redirect_to_the_right_pages(): void
    {
        $account = $this->createAccountWithPlan('starter');
        $this->actingAsAccountOwner($account);

        $this->get('/inbox')->assertRedirect('/app/conversations');
        $this->get('/settings')->assertRedirect('/app/settings');
        $this->get('/app/settings/team')->assertRedirect(route('app.team.index'));
        $this->get('/app/settings/security')->assertRedirect('/app/settings?tab=security');
    }

    public function test_dashboard_shares_a_setup_checklist(): void
    {
        $account = $this->createAccountWithPlan('starter');
        $this->actingAsAccountOwner($account);

        $this->get(route('app.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('App/Dashboard')
                ->has('setup.steps', 4)
                ->where('setup.completed', 0));
    }

}
