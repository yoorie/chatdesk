<?php

namespace Database\Seeders;

use App\Models\AccountApiKey;
use App\Models\AccountApiRequestLog;
use App\Models\AccountAppointment;
use App\Models\AccountWebhookDelivery;
use App\Models\AccountWebhookEndpoint;
use App\Models\AiAgent;
use App\Models\AiAgentRun;
use App\Models\AiUsageLog;
use App\Models\Plan;
use App\Models\QuickReply;
use App\Models\User;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotEdge;
use App\Modules\Chatbots\Models\BotFlow;
use App\Modules\Chatbots\Models\BotNode;
use App\Modules\Contacts\Models\ContactTag;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use App\Services\AccountProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Sample workspace for a public preview: owner, agent, inbox, a spaced
 * chatbot, AI assistants, and developer keys. Passwords come from config/demo.php.
 */
class DemoInboxSeeder extends Seeder
{
    public function run(): void
    {
        $owner = $this->user(
            (string) config('demo.user_email'),
            'Meera Shah',
            (string) config('demo.user_password'),
            false
        );
        $owner->forceFill(['ai_suggestions_enabled' => true])->save();

        $agent = $this->user(
            (string) config('demo.agent_email'),
            'Rohan Iyer',
            (string) config('demo.agent_password'),
            false
        );

        $this->user(
            (string) config('demo.admin_email'),
            'Super Admin',
            (string) config('demo.admin_password'),
            true
        );

        DB::transaction(function () use ($owner, $agent) {
            $account = $owner->ownedAccounts()->first()
                ?? app(AccountProvisioner::class)->create($owner, 'Harbour & Co.', 'pro', [
                    'workspace_type' => 'business',
                    'industry' => 'Retail',
                    'timezone' => config('app.timezone', 'UTC'),
                ]);

            $this->keepProActive($account);

            $account->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner'],
                $agent->id => ['role' => 'member'],
            ]);
            $account->users()->updateExistingPivot($owner->id, ['role' => 'owner']);
            $account->users()->updateExistingPivot($agent->id, ['role' => 'member']);

            $connection = $this->connection($account);
            $conversations = $this->inbox($account, $connection, $owner, $agent);
            $this->flow($account, $owner);
            $agents = $this->assistants($account, $owner, $conversations);
            $this->developer($account, $connection, $agents);
        });

        $this->command?->info('Demo workspace ready for '.config('demo.user_email').'.');
    }

    private function user(string $email, string $name, string $password, bool $platformAdmin): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'is_platform_admin' => $platformAdmin,
            ]
        );
        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }

    private function keepProActive($account): void
    {
        $plan = Plan::where('key', 'pro')->first();
        $subscription = $account->subscription;
        if (! $subscription || ! $plan) {
            return;
        }

        $subscription->forceFill([
            'plan_id' => $plan->id,
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->addYear(),
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'provider' => 'manual',
        ])->save();
    }

    private function connection($account): WhatsAppConnection
    {
        $connection = WhatsAppConnection::where('account_id', $account->id)
            ->where('phone_number_id', 'demo_phone_001')
            ->first();

        if (! $connection) {
            $connection = new WhatsAppConnection([
                'account_id' => $account->id,
                'name' => 'Harbour WhatsApp',
                'waba_id' => 'demo_waba_001',
                'phone_number_id' => 'demo_phone_001',
                'business_phone' => '+1 555 0100',
                'api_version' => 'v20.0',
                'webhook_verify_token' => WhatsAppConnection::generateVerifyToken(),
                'webhook_subscribed' => true,
                'webhook_last_received_at' => now()->subMinutes(8),
                'is_active' => true,
                'setup_method' => 'manual',
                'quality_rating' => 'GREEN',
            ]);
            $connection->access_token = 'demo-token';
            $connection->save();
        }

        return $connection;
    }

    /**
     * @return array<string, WhatsAppConversation>
     */
    private function inbox($account, WhatsAppConnection $connection, User $owner, User $agent): array
    {
        $samples = [
            [
                'name' => 'Priya Nair',
                'wa_id' => '15550100001',
                'email' => 'priya.nair@example.com',
                'phone' => '+1 555 010 0001',
                'status' => 'active',
                'conversation_status' => 'open',
                'priority' => 'urgent',
                'assignee' => $agent->id,
                'messages' => [
                    ['inbound', 'Hi, I placed an order yesterday. Can you confirm the delivery date?', 42],
                    ['outbound', 'Hi Priya, your order is packed and will be delivered tomorrow between 11 AM and 2 PM.', 38],
                    ['inbound', 'Great, please share the tracking link when available.', 9],
                ],
            ],
            [
                'name' => 'Arjun Mehta',
                'wa_id' => '15550100002',
                'email' => 'arjun.mehta@example.com',
                'phone' => '+1 555 010 0002',
                'status' => 'active',
                'conversation_status' => 'pending',
                'priority' => 'normal',
                'assignee' => null,
                'messages' => [
                    ['inbound', 'Do you have the festival bundle in size L?', 90],
                    ['outbound', 'Yes, size L is available. I can reserve one for you for the next 30 minutes.', 74],
                ],
            ],
            [
                'name' => 'Sneha Kapoor',
                'wa_id' => '15550100003',
                'email' => 'sneha.kapoor@example.com',
                'phone' => '+1 555 010 0003',
                'status' => 'active',
                'conversation_status' => 'open',
                'priority' => 'normal',
                'assignee' => $owner->id,
                'messages' => [
                    ['inbound', 'Can you send today sale catalog?', 180],
                    ['outbound', 'Sure Sneha, here is the latest catalog. The green-tag offers end tonight.', 170],
                    ['inbound', 'Thanks. I am checking it now.', 155],
                ],
            ],
            [
                'name' => 'Rahul Verma',
                'wa_id' => '15550100004',
                'email' => 'rahul.verma@example.com',
                'phone' => '+1 555 010 0004',
                'status' => 'active',
                'conversation_status' => 'closed',
                'priority' => 'low',
                'assignee' => $owner->id,
                'messages' => [
                    ['inbound', 'Payment done, please confirm.', 1440],
                    ['outbound', 'Confirmed Rahul. Your invoice and order confirmation have been sent.', 1415],
                ],
            ],
        ];

        $conversations = [];
        foreach ($samples as $sample) {
            $contact = WhatsAppContact::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'wa_id' => $sample['wa_id'],
                ],
                [
                    'name' => $sample['name'],
                    'email' => $sample['email'],
                    'phone' => $sample['phone'],
                    'status' => $sample['status'],
                    'source' => 'demo',
                    'last_seen_at' => now()->subMinutes($sample['messages'][array_key_last($sample['messages'])][2]),
                    'metadata' => [
                        'demo' => true,
                        'tags' => ['vip', 'whatsapp'],
                    ],
                ]
            );

            $lastMessage = $sample['messages'][array_key_last($sample['messages'])];
            $conversation = WhatsAppConversation::updateOrCreate(
                [
                    'account_id' => $account->id,
                    'whatsapp_connection_id' => $connection->id,
                    'whatsapp_contact_id' => $contact->id,
                ],
                [
                    'assigned_to' => $sample['assignee'],
                    'status' => $sample['conversation_status'],
                    'priority' => $sample['priority'],
                    'last_message_at' => now()->subMinutes($lastMessage[2]),
                    'last_message_preview' => $lastMessage[1],
                    'metadata' => ['demo' => true],
                ]
            );

            WhatsAppMessage::where('account_id', $account->id)
                ->where('whatsapp_conversation_id', $conversation->id)
                ->where('payload->demo_seed', true)
                ->delete();

            foreach ($sample['messages'] as $index => [$direction, $body, $minutesAgo]) {
                $timestamp = now()->subMinutes($minutesAgo);
                WhatsAppMessage::forceCreate([
                    'account_id' => $account->id,
                    'whatsapp_conversation_id' => $conversation->id,
                    'direction' => $direction,
                    'meta_message_id' => 'demo_'.$conversation->id.'_'.$index.'_'.$timestamp->format('YmdHi'),
                    'type' => 'text',
                    'text_body' => $body,
                    'payload' => ['demo_seed' => true],
                    'status' => $direction === 'outbound' ? 'delivered' : 'read',
                    'sent_at' => $direction === 'outbound' ? $timestamp : null,
                    'delivered_at' => $direction === 'outbound' ? $timestamp->copy()->addMinute() : null,
                    'read_at' => $direction === 'outbound' ? $timestamp->copy()->addMinutes(2) : null,
                    'received_at' => $direction === 'inbound' ? $timestamp : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }

            $contact->forceFill([
                'message_count' => count($sample['messages']),
                'last_contacted_at' => Carbon::parse($conversation->last_message_at),
            ])->save();

            $conversations[$sample['wa_id']] = $conversation;
        }

        ContactTag::updateOrCreate(
            ['account_id' => $account->id, 'name' => 'VIP'],
            ['color' => '#047857', 'description' => 'Customers who order often']
        );

        QuickReply::updateOrCreate(
            ['account_id' => $account->id, 'shortcut' => 'order_status'],
            [
                'type' => 'reply',
                'label' => 'Order status',
                'message' => 'Your order is packed and leaves the warehouse tomorrow morning.',
                'is_active' => true,
                'usage_count' => 18,
                'created_by' => $owner->id,
            ]
        );

        return $conversations;
    }

    private function flow($account, User $owner): void
    {
        $bot = Bot::updateOrCreate(
            ['account_id' => $account->id, 'name' => 'Order help'],
            [
                'description' => 'Answers order questions and hands everything else to the team.',
                'status' => 'active',
                'is_default' => true,
                'applies_to' => ['all_connections' => true, 'connection_ids' => []],
                'stop_on_first_flow' => true,
                'session_timeout_minutes' => 30,
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ]
        );

        $flow = BotFlow::updateOrCreate(
            ['bot_id' => $bot->id, 'name' => 'Order or support'],
            [
                'account_id' => $account->id,
                'trigger' => [
                    'type' => 'keyword',
                    'keywords' => ['hi', 'order', 'help'],
                    'match_type' => 'any',
                    'case_sensitive' => false,
                    'whole_word' => false,
                ],
                'enabled' => true,
                'priority' => 10,
            ]
        );

        $flow->edges()->delete();
        $flow->nodes()->delete();

        // Cards are 248px wide. Keep at least 320px between columns and 200px between rows.
        $layout = [
            ['action', ['is_start' => true, 'action_type' => 'send_text', 'message' => 'Hi! Reply ORDER for a delivery update, or tell us what you need.'], 40, 40],
            ['condition', ['type' => 'text_contains', 'value' => 'order', 'case_sensitive' => false], 400, 40],
            ['action', ['action_type' => 'send_text', 'message' => 'Your order is packed and will be delivered tomorrow between 11 AM and 2 PM.'], 760, 40],
            ['action', ['action_type' => 'handoff', 'reason' => 'Customer asked for a person'], 760, 280],
        ];

        $ids = [];
        foreach ($layout as $index => [$type, $config, $x, $y]) {
            $ids[] = BotNode::create([
                'account_id' => $account->id,
                'bot_flow_id' => $flow->id,
                'type' => $type,
                'config' => $config,
                'sort_order' => $index + 1,
                'pos_x' => $x,
                'pos_y' => $y,
            ])->id;
        }

        foreach ([[0, 1, 'next'], [1, 2, 'true'], [1, 3, 'false']] as $order => [$from, $to, $label]) {
            BotEdge::create([
                'account_id' => $account->id,
                'bot_flow_id' => $flow->id,
                'from_node_id' => $ids[$from],
                'to_node_id' => $ids[$to],
                'label' => $label,
                'sort_order' => $order + 1,
            ]);
        }
    }

    /**
     * @param  array<string, WhatsAppConversation>  $conversations
     * @return array<string, AiAgent>
     */
    private function assistants($account, User $owner, array $conversations): array
    {
        $maya = AiAgent::updateOrCreate(
            ['account_id' => $account->id, 'slug' => 'maya'],
            [
                'created_by' => $owner->id,
                'name' => 'Maya',
                'avatar' => '🌿',
                'role' => 'support',
                'language' => 'en',
                'tone' => 'friendly',
                'mode' => 'suggest',
                'is_active' => true,
                'instructions' => 'Help with order status, delivery windows, and returns. Keep replies under three sentences.',
                'goal' => 'Resolve delivery questions before handing off.',
                'fallback_reply' => 'I will ask a teammate to take a look and reply shortly.',
                'max_auto_replies_per_conversation' => 3,
                'max_reply_chars' => 500,
                'confidence_threshold' => 0.7,
                'last_used_at' => now()->subMinutes(20),
            ]
        );

        $leah = AiAgent::updateOrCreate(
            ['account_id' => $account->id, 'slug' => 'leah'],
            [
                'created_by' => $owner->id,
                'name' => 'Leah',
                'avatar' => '✨',
                'role' => 'sales',
                'language' => 'en',
                'tone' => 'concise',
                'mode' => 'approval',
                'is_active' => true,
                'instructions' => 'Suggest a product from the current catalog and ask one follow-up question.',
                'goal' => 'Help shoppers choose a size or bundle.',
                'fallback_reply' => 'A specialist can confirm stock for you.',
                'max_auto_replies_per_conversation' => 2,
                'max_reply_chars' => 400,
                'confidence_threshold' => 0.75,
                'last_used_at' => now()->subHours(5),
            ]
        );

        AiUsageLog::where('account_id', $account->id)->where('user_id', $owner->id)->delete();
        foreach (range(1, 12) as $ignored) {
            AiUsageLog::create([
                'user_id' => $owner->id,
                'account_id' => $account->id,
                'feature' => 'conversation_suggest',
                'created_at' => now()->subDays(2),
            ]);
        }
        foreach (range(1, 4) as $ignored) {
            AiUsageLog::create([
                'user_id' => $owner->id,
                'account_id' => $account->id,
                'feature' => 'support_reply',
                'created_at' => now()->subDay(),
            ]);
        }

        AiAgentRun::where('account_id', $account->id)->whereIn('ai_agent_id', [$maya->id, $leah->id])->delete();
        $priya = $conversations['15550100001'] ?? null;
        if ($priya) {
            AiAgentRun::create([
                'account_id' => $account->id,
                'ai_agent_id' => $maya->id,
                'whatsapp_conversation_id' => $priya->id,
                'status' => 'sent',
                'reason' => 'Drafted a delivery update for Priya Nair',
                'suggestion' => 'Your order is packed and will arrive tomorrow between 11 AM and 2 PM.',
            ]);
            AiAgentRun::create([
                'account_id' => $account->id,
                'ai_agent_id' => $leah->id,
                'whatsapp_conversation_id' => $priya->id,
                'status' => 'skipped',
                'reason' => 'Waiting for a teammate to approve the product suggestion',
            ]);
        }

        return ['maya' => $maya, 'leah' => $leah];
    }

    /**
     * @param  array<string, AiAgent>  $agents
     */
    private function developer($account, WhatsAppConnection $connection, array $agents): void
    {
        $template = WhatsAppTemplate::updateOrCreate(
            ['account_id' => $account->id, 'name' => 'order_update'],
            [
                'whatsapp_connection_id' => $connection->id,
                'language' => 'en',
                'category' => 'UTILITY',
                'status' => 'approved',
                'body_text' => 'Hi {{1}}, your order {{2}} is on the way and arrives {{3}}.',
                'footer_text' => 'Harbour & Co.',
                'components' => [
                    ['type' => 'BODY', 'text' => 'Hi {{1}}, your order {{2}} is on the way and arrives {{3}}.'],
                    ['type' => 'FOOTER', 'text' => 'Harbour & Co.'],
                ],
                'last_synced_at' => now()->subHour(),
            ]
        );

        Campaign::updateOrCreate(
            ['account_id' => $account->id, 'name' => 'Weekend restock'],
            [
                'whatsapp_connection_id' => $connection->id,
                'whatsapp_template_id' => $template->id,
                'created_by' => $account->owner_id,
                'slug' => 'weekend-restock',
                'description' => 'Sent to customers who bought last month.',
                'status' => 'completed',
                'type' => 'template',
                'recipient_type' => 'contacts',
                'total_recipients' => 128,
                'sent_count' => 126,
                'delivered_count' => 121,
                'read_count' => 84,
                'failed_count' => 2,
                'respect_opt_out' => true,
                'started_at' => now()->subDays(3),
                'completed_at' => now()->subDays(3)->addHour(),
            ]
        );

        AccountAppointment::updateOrCreate(
            ['account_id' => $account->id, 'external_id' => 'demo-fitting'],
            [
                'external_source' => 'demo',
                'title' => 'Showroom fitting',
                'contact_name' => 'Sneha Kapoor',
                'contact_phone' => '+1 555 010 0003',
                'scheduled_at' => now()->addDays(2)->setTime(15, 0),
                'duration_minutes' => 30,
                'staff_name' => 'Rohan Iyer',
                'status' => 'scheduled',
                'type' => 'in_person',
                'location' => 'Harbour & Co. showroom',
            ]
        );

        $key = AccountApiKey::updateOrCreate(
            ['account_id' => $account->id, 'name' => 'Storefront'],
            [
                'token_prefix' => 'wfy_live_demokey',
                'token_hash' => hash('sha256', 'demo-storefront-key'),
                'scopes' => ['connections:read', 'templates:read', 'conversations:read', 'messages:write'],
                'last_used_at' => now()->subHours(2),
                'last_used_ip' => '203.0.113.24',
                'revoked_at' => null,
            ]
        );

        AccountApiRequestLog::where('account_id', $account->id)->where('request_id', 'like', 'demo-%')->delete();
        AccountApiRequestLog::create([
            'account_id' => $account->id,
            'account_api_key_id' => $key->id,
            'method' => 'GET',
            'path' => '/api/v1/whatsapp/connections',
            'route_name' => 'api.connections.index',
            'status' => 200,
            'duration_ms' => 42,
            'ip' => '203.0.113.24',
            'request_id' => 'demo-req-connections',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
        AccountApiRequestLog::create([
            'account_id' => $account->id,
            'account_api_key_id' => $key->id,
            'method' => 'POST',
            'path' => '/api/v1/whatsapp/messages',
            'route_name' => 'api.messages.store',
            'status' => 201,
            'duration_ms' => 118,
            'ip' => '203.0.113.24',
            'request_id' => 'demo-req-message',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $endpoint = AccountWebhookEndpoint::updateOrCreate(
            ['account_id' => $account->id, 'url' => 'https://example.com/hooks/chatdesk'],
            [
                'secret' => 'demo-webhook-secret',
                'events' => ['message.received', 'message.sent', 'message.delivered'],
                'is_enabled' => true,
                'last_tested_at' => now()->subMinutes(40),
                'last_status' => 200,
                'last_error' => null,
            ]
        );

        AccountWebhookDelivery::where('account_webhook_endpoint_id', $endpoint->id)
            ->where('delivery_id', 'like', 'demo-%')
            ->delete();
        AccountWebhookDelivery::create([
            'account_id' => $account->id,
            'account_webhook_endpoint_id' => $endpoint->id,
            'delivery_id' => 'demo-delivery-received',
            'event' => 'message.received',
            'url' => $endpoint->url,
            'status' => 200,
            'attempts' => 1,
            'duration_ms' => 86,
            'payload' => ['demo' => true, 'agent' => $agents['maya']->name],
            'delivered_at' => now()->subMinutes(12),
        ]);
    }
}
