<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fallback_error_views_share_styles_without_booting_the_app(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $html = view('errors.'.$status)->render();
            $this->assertStringContainsString('class="error-page"', $html);
            $this->assertStringContainsString('class="error-card"', $html);
            $this->assertStringContainsString('.error-page {', $html);
            $this->assertStringNotContainsString('resources/js/app.tsx', $html);
            $this->assertStringNotContainsString('type="module"', $html);
            $this->assertSame(1, substr_count($html, '<h1 '));
        }
    }

    public function test_inertia_errors_keep_their_status_and_use_matching_pages(): void
    {
        Route::middleware('web')->get('/_test/error/{status}', fn (int $status) => abort($status));
        foreach ([403 => 'Forbidden', 404 => 'NotFound', 419 => 'SessionExpired', 429 => 'RateLimited', 500 => 'ServerError', 503 => 'Maintenance'] as $status => $page) {
            $this->get('/_test/error/'.$status, ['X-Inertia' => 'true', 'X-Inertia-Version' => app(\App\Http\Middleware\HandleInertiaRequests::class)->version(\Illuminate\Http\Request::create('/'))])
                ->assertStatus($status)
                ->assertJsonPath('component', 'Error/'.$page);
        }
        $this->getJson('/_test/error/404')->assertNotFound()->assertJsonStructure(['message']);
    }
}
