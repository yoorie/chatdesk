<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $starter = Plan::updateOrCreate(
            ['key' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'For small teams',
                'price_monthly' => 99900,
                'price_yearly' => 999000,
                'currency' => 'INR',
                'is_active' => true,
                'is_public' => true,
                'trial_days' => 7,
                'sort_order' => 1,
                'limits' => [
                    'agents' => 3,
                    'whatsapp_connections' => 1,
                    'messages_monthly' => 5000,
                    'template_sends_monthly' => 1000,
                    'ai_credits_monthly' => 0,
                    'retention_days' => 90,
                ],
                'modules' => ['whatsapp.cloud', 'templates', 'contacts', 'broadcasts'],
                'metadata' => [
                    'public_features' => [
                        '1 WhatsApp connection per workspace',
                        '3 agents',
                        '5,000 messages/month',
                        '1,000 template sends/month',
                        'Contacts, segments, and broadcasts',
                        '90-day data retention',
                        '7-day free trial',
                    ],
                ],
            ]
        );

        Plan::updateOrCreate(
            ['key' => 'pro'],
            [
                'name' => 'Pro',
                'description' => 'For growing businesses',
                'price_monthly' => 199900,
                'price_yearly' => 1999000,
                'currency' => 'INR',
                'is_active' => true,
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 2,
                'limits' => [
                    'agents' => 8,
                    'whatsapp_connections' => 1,
                    'messages_monthly' => 25000,
                    'template_sends_monthly' => 5000,
                    'ai_credits_monthly' => 500,
                    'retention_days' => 365,
                ],
                'modules' => ['whatsapp.cloud', 'templates', 'automation.chatbots', 'ai', 'analytics', 'floaters', 'contacts', 'broadcasts'],
                'metadata' => [
                    'public_features' => [
                        'Everything in Starter',
                        '8 agents',
                        '25,000 messages/month',
                        '5,000 template sends/month',
                        'Visual automation builder',
                        'AI agents and suggestions',
                        'Campaign analytics and widgets',
                        '365-day data retention',
                    ],
                ],
            ]
        );

        Plan::updateOrCreate(
            ['key' => 'business'],
            [
                'name' => 'Business',
                'description' => 'For teams scaling conversations',
                'price_monthly' => 349900,
                'price_yearly' => 3499000,
                'currency' => 'INR',
                'is_active' => true,
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 3,
                'limits' => [
                    'agents' => 15,
                    'whatsapp_connections' => 1,
                    'messages_monthly' => 50000,
                    'template_sends_monthly' => 10000,
                    'ai_credits_monthly' => 1500,
                    'retention_days' => 365,
                ],
                'modules' => ['whatsapp.cloud', 'templates', 'automation.chatbots', 'ai', 'whatsapp.calling', 'analytics', 'floaters', 'billing', 'contacts', 'broadcasts'],
                'metadata' => [
                    'public_features' => [
                        'Everything in Pro',
                        '15 agents',
                        '50,000 messages/month',
                        '10,000 template sends/month',
                        '1,500 AI credits/month',
                        'WhatsApp Calling / AI call assistant',
                        'Advanced automation and handoff controls',
                        'Priority billing and workspace operations',
                    ],
                ],
            ]
        );

        Plan::where('key', 'enterprise')->first()?->update([
            'price_monthly' => null,
            'price_yearly' => null,
            'is_active' => true,
            'is_public' => true,
            'trial_days' => 0,
            'sort_order' => 4,
            'metadata' => [
                'custom' => true,
                'public_features' => [
                    'Custom usage limits',
                    'Admin-approved activation',
                    'Dedicated onboarding',
                    'Meta readiness support',
                    'Priority operations support',
                ],
            ],
        ]);

        $free = Plan::where('key', 'free')->first();
        if ($free) {
            DB::table('subscriptions')
                ->where('plan_id', $free->id)
                ->update(['plan_id' => $starter->id]);

            $free->update([
                'is_active' => false,
                'is_public' => false,
                'sort_order' => 99,
            ]);
        }
    }

    public function down(): void
    {
        Plan::where('key', 'business')->delete();

        Plan::where('key', 'pro')->update([
            'price_monthly' => 499900,
            'price_yearly' => 4999000,
            'sort_order' => 3,
        ]);

        Plan::where('key', 'starter')->update([
            'price_monthly' => 99900,
            'price_yearly' => 999000,
            'sort_order' => 2,
        ]);

        Plan::where('key', 'free')->update([
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);
    }
};
