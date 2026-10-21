<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('onboarding', absolute: false));
    }

    public function test_super_admins_redirect_to_platform_dashboard_after_login(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('platform.dashboard', absolute: false));
    }

    public function test_super_admins_do_not_keep_app_panel_intended_url_after_login(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $response = $this
            ->withSession(['url.intended' => url('/app/dashboard')])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('platform.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_blank_inertia_login_submission_redirects_back_with_validation_errors(): void
    {
        $response = $this
            ->from('/login')
            ->withHeader('X-Inertia', 'true')
            ->post('/login', [
                'email' => '',
                'password' => '',
            ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
