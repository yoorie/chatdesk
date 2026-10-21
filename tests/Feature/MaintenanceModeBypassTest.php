<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceModeBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function enableMaintenanceMode(): void
    {
        PlatformSetting::set(
            'general.maintenance_mode',
            true,
            'boolean',
            'general'
        );
        PlatformSetting::set(
            'general.maintenance_message',
            'Down for scheduled work.',
            'string',
            'general'
        );
    }

    public function test_register_page_bypasses_maintenance_mode(): void
    {
        $this->enableMaintenanceMode();

        $this->get('/register')
            ->assertOk();
    }

    public function test_verify_email_prompt_bypasses_maintenance_mode(): void
    {
        $this->enableMaintenanceMode();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk();
    }

    public function test_home_is_blocked_during_maintenance_for_guests(): void
    {
        $this->enableMaintenanceMode();

        $this->get('/')
            ->assertStatus(503);
    }
}
