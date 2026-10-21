<?php

namespace Tests\Feature;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_core_user_pages_render_for_workspace_owner(): void
    {
        $account = $this->createAccountWithPlan('self-hosted');
        $this->actingAsAccountOwner($account);
        WhatsAppConnection::factory()->create([
            'account_id' => $account->id,
            'is_active' => true,
        ]);

        $routes = [
            ['app.dashboard', 'App/Dashboard'],
            ['app.whatsapp.conversations.index', 'WhatsApp/Conversations/Index'],
            ['app.whatsapp.templates.index', 'WhatsApp/Templates/Index'],
            ['app.broadcasts.index', 'Broadcasts/Index'],
            ['app.whatsapp.connections.index', 'WhatsApp/Connections/Index'],
            ['app.developer.index', 'Developer/Index'],
            ['app.contacts.index', 'Contacts/Index'],
            ['app.chatbots.index', 'Chatbots/Index'],
            ['app.team.index', 'App/Team/Index'],
            ['app.settings', 'Settings/Index'],
        ];

        foreach ($routes as [$routeName, $component]) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component($component));
        }
    }
}
