<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CloudApiSetupTest extends TestCase
{
    public function test_only_manual_official_cloud_api_connection_routes_are_registered(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => $route->getName())->filter();
        $this->assertFalse($names->contains(fn ($name) => str_contains($name, 'baileys') || str_contains($name, 'connections.qr')));
        $this->assertTrue(Route::has('app.whatsapp.connections.store'));
        $this->assertFalse(Route::has('app.whatsapp.connections.store-embedded'));
    }

    public function test_saas_layer_routes_are_not_registered(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => (string) $route->getName())->filter();
        foreach (['platform.', 'app.billing.', 'app.workspaces.', 'onboarding', 'pricing', 'app.integrations.'] as $prefix) {
            $this->assertFalse($names->contains(fn ($name) => str_starts_with($name, $prefix)), "Route {$prefix}* should not exist");
        }
    }
}
