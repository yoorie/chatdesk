<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * ChatDesk open-source edition: internal unlimited mode.
 *
 * Seeds the single internal plan the organisation runs on. It is never shown,
 * sold or charged; it only tells the module registry that every bundled module
 * is available. Limits are -1 (= unlimited) and EntitlementService ignores them.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $modules = Module::query()->pluck('key')->all();

        Plan::updateOrCreate(['key' => 'self-hosted'], [
            'name' => 'Self-hosted',
            'description' => 'ChatDesk open-source edition (internal, unlimited)',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'USD',
            'is_active' => true,
            'is_public' => false,
            'trial_days' => 0,
            'sort_order' => 1,
            'limits' => [
                'agents' => -1,
                'whatsapp_connections' => -1,
                'messages_monthly' => -1,
                'template_sends_monthly' => -1,
                'ai_credits_monthly' => -1,
                'retention_days' => -1,
            ],
            'modules' => $modules,
            'metadata' => ['internal' => true],
        ]);

        // Remove plans left over from older installs.
        Plan::where('key', '!=', 'self-hosted')->whereDoesntHave('subscriptions')->delete();
    }
}
