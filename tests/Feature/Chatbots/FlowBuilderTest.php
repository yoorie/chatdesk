<?php

namespace Tests\Feature\Chatbots;

use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotEdge;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\Chatbots\Services\BotContext;
use App\Modules\Chatbots\Services\TriggerEvaluator;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FlowBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'ModuleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    public function test_short_keyword_does_not_match_inside_another_word(): void
    {
        $flow = new BotFlow([
            'trigger' => [
                'type' => 'keyword',
                'keywords' => ['hi', 'pay'],
                'match_type' => 'any',
                'whole_word' => false,
                'case_sensitive' => false,
            ],
        ]);

        $context = new BotContext(
            account: new \App\Models\Account(['id' => 1]),
            conversation: new WhatsAppConversation(['id' => 1, 'account_id' => 1]),
            inboundMessage: new WhatsAppMessage(['text_body' => 'Hindi me baat kar sakte ho kya']),
            connection: new WhatsAppConnection(['id' => 1, 'account_id' => 1]),
        );

        $this->assertFalse(app(TriggerEvaluator::class)->matches($flow, $context));
    }

    public function test_short_keyword_still_matches_as_own_word(): void
    {
        $flow = new BotFlow([
            'trigger' => [
                'type' => 'keyword',
                'keywords' => ['hi'],
                'match_type' => 'any',
                'whole_word' => false,
                'case_sensitive' => false,
            ],
        ]);

        $context = new BotContext(
            account: new \App\Models\Account(['id' => 1]),
            conversation: new WhatsAppConversation(['id' => 1, 'account_id' => 1]),
            inboundMessage: new WhatsAppMessage(['text_body' => 'Hi, need pricing']),
            connection: new WhatsAppConnection(['id' => 1, 'account_id' => 1]),
        );

        $this->assertTrue(app(TriggerEvaluator::class)->matches($flow, $context));
    }

    public function test_owner_can_build_and_save_a_flow_graph_from_builder_endpoints(): void
    {
        $account = $this->createAccountWithPlan('pro');
        $owner = $this->actingAsAccountOwner($account);

        $this->post(route('app.chatbots.store'), [
            'name' => 'Builder QA Bot',
            'description' => 'Created by the flow builder test',
            'status' => 'draft',
            'applies_to' => [
                'all_connections' => true,
                'connection_ids' => [],
            ],
            'stop_on_first_flow' => true,
            'starter_flow_mode' => 'guided',
            'starter_trigger_type' => 'keyword',
            'starter_keywords' => 'help, support',
            'starter_reply_message' => 'How can we help?',
        ])->assertRedirect();

        $bot = Bot::where('account_id', $account->id)->where('name', 'Builder QA Bot')->firstOrFail();
        $flow = $bot->flows()->firstOrFail();
        $startNode = $flow->nodes()->firstOrFail();

        $this->patch(route('app.chatbots.nodes.update', ['node' => $startNode->id]), [
            'type' => 'action',
            'config' => [
                'is_start' => true,
                'action_type' => 'send_text',
                'message' => 'Updated from builder',
            ],
            'pos_x' => 120,
            'pos_y' => 160,
        ])->assertRedirect();

        $this->post(route('app.chatbots.nodes.store', ['flow' => $flow->id]), [
            'type' => 'condition',
            'config' => [
                'type' => 'text_contains',
                'value' => 'pricing',
                'case_sensitive' => false,
            ],
            'sort_order' => 2,
            'pos_x' => 430,
            'pos_y' => 160,
        ])->assertRedirect();

        $conditionNode = BotNode::where('bot_flow_id', $flow->id)->where('type', 'condition')->firstOrFail();

        $this->post(route('app.chatbots.edges.store', ['flow' => $flow->id]), [
            'from_node_id' => $startNode->id,
            'to_node_id' => $conditionNode->id,
            'label' => 'next',
            'sort_order' => 1,
        ])->assertRedirect();

        $edge = BotEdge::where('bot_flow_id', $flow->id)->firstOrFail();

        $this->patch(route('app.chatbots.edges.update', ['edge' => $edge->id]), [
            'label' => 'true',
        ])->assertRedirect();

        $this->patch(route('app.chatbots.flows.update', ['flow' => $flow->id]), [
            'nodes' => [
                [
                    'id' => $startNode->id,
                    'type' => 'action',
                    'config' => [
                        'is_start' => true,
                        'action_type' => 'send_text',
                        'message' => 'Updated from builder',
                    ],
                    'sort_order' => 1,
                    'pos_x' => 140,
                    'pos_y' => 180,
                ],
                [
                    'id' => $conditionNode->id,
                    'type' => 'condition',
                    'config' => [
                        'type' => 'text_contains',
                        'value' => 'pricing',
                        'case_sensitive' => false,
                    ],
                    'sort_order' => 2,
                    'pos_x' => 460,
                    'pos_y' => 180,
                ],
            ],
            'edges' => [
                [
                    'id' => $edge->id,
                    'from_node_id' => $startNode->id,
                    'to_node_id' => $conditionNode->id,
                    'label' => 'true',
                    'sort_order' => 1,
                ],
            ],
        ])->assertRedirect();

        $this->post(route('app.chatbots.nodes.store', ['flow' => $flow->id]), [
            'type' => 'delay',
            'config' => ['seconds' => 60],
            'sort_order' => 3,
            'pos_x' => 760,
            'pos_y' => 180,
        ])->assertRedirect();

        $delayNode = BotNode::where('bot_flow_id', $flow->id)->where('type', 'delay')->firstOrFail();

        $this->delete(route('app.chatbots.nodes.destroy', ['node' => $delayNode->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('bot_nodes', [
            'id' => $startNode->id,
            'pos_x' => 140,
            'pos_y' => 180,
        ]);
        $this->assertDatabaseHas('bot_nodes', [
            'id' => $conditionNode->id,
            'type' => 'condition',
            'pos_x' => 460,
            'pos_y' => 180,
        ]);
        $this->assertDatabaseHas('bot_edges', [
            'bot_flow_id' => $flow->id,
            'from_node_id' => $startNode->id,
            'to_node_id' => $conditionNode->id,
            'label' => 'true',
        ]);
        $this->assertDatabaseMissing('bot_nodes', [
            'id' => $delayNode->id,
        ]);

        $this->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('app.chatbots.index', ['bot' => $bot->id]))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Chatbots/Index')
                ->where('selectedBot.id', $bot->id)
                ->has('selectedBot.flows.0.nodes', 2)
                ->has('selectedBot.flows.0.edges', 1)
                ->where('selectedBot.flows.0.nodes.0.pos_x', 140)
                ->where('selectedBot.flows.0.edges.0.label', 'true')
            );
    }
}
