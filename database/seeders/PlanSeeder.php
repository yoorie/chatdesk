<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'key' => 'starter',
                'name' => 'Starter',
                'description' => 'For small teams',
                'price_monthly' => 99900, // ₹999
                'price_yearly' => 999000, // ₹9990 (2 months free)
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
            ],
            [
                'key' => 'pro',
                'name' => 'Pro',
                'description' => 'For growing businesses',
                'price_monthly' => 199900, // ₹1999
                'price_yearly' => 1999000, // ₹19990
                'currency' => 'INR',
                'is_active' => true,
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 2,
                'limits' => [
                    'agents' => 8,
                    'whatsapp_connections' => 3,
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
                        '3 WhatsApp numbers',
                        '25,000 messages/month',
                        '5,000 template sends/month',
                        'Visual automation builder',
                        'AI agents and suggestions',
                        'Campaign analytics and widgets',
                        '365-day data retention',
                    ],
                ],
            ],
            [
                'key' => 'business',
                'name' => 'Business',
                'description' => 'For teams scaling conversations',
                'price_monthly' => 349900, // ₹3499
                'price_yearly' => 3499000, // ₹34990
                'currency' => 'INR',
                'is_active' => true,
                'is_public' => true,
                'trial_days' => 14,
                'sort_order' => 3,
                'limits' => [
                    'agents' => 15,
                    'whatsapp_connections' => 10,
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
                        '10 WhatsApp numbers',
                        '50,000 messages/month',
                        '10,000 template sends/month',
                        '1,500 AI credits/month',
                        'WhatsApp Calling / AI call assistant',
                        'Advanced automation and handoff controls',
                        'Priority billing and workspace operations',
                    ],
                ],
            ],
            [
                'key' => 'enterprise',
                'name' => 'Enterprise',
                'description' => 'Custom solutions for large organizations',
                'price_monthly' => null, // Custom pricing
                'price_yearly' => null,
                'currency' => 'INR',
                'is_active' => true,
                'is_public' => true,
                'trial_days' => 0,
                'sort_order' => 4,
                'limits' => [
                    'agents' => -1, // Unlimited
                    'whatsapp_connections' => -1,
                    'messages_monthly' => -1,
                    'template_sends_monthly' => -1,
                    'ai_credits_monthly' => -1,
                    'retention_days' => -1,
                ],
                'modules' => ['whatsapp.cloud', 'templates', 'automation.chatbots', 'ai', 'whatsapp.calling', 'analytics', 'floaters', 'billing', 'contacts', 'broadcasts'],
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
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['key' => $planData['key']],
                $planData
            );
        }

        Plan::where('key', 'free')->doesntHave('subscriptions')->delete();
        Plan::where('key', 'free')->update([
            'is_active' => false,
            'is_public' => false,
            'sort_order' => 99,
        ]);

        $this->command->info('Plans seeded successfully.');
    }
}
