<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Console\Command;

class EnsureBroadcastsModule extends Command
{
    protected $signature = 'broadcasts:ensure-module';

    protected $description = 'Ensure the Broadcasts/Campaigns module exists and is included in plans (starter, pro, enterprise).';

    public function handle(): int
    {
        $this->info('Ensuring Broadcasts module exists...');

        Module::updateOrCreate(
            ['key' => 'broadcasts'],
            [
                'name' => 'Broadcasts & Campaigns',
                'description' => 'Create and manage WhatsApp broadcast campaigns',
                'is_core' => false,
                'is_enabled' => true,
            ]
        );
        $this->line('  Module "broadcasts" is enabled.');

        $planKeys = ['starter', 'pro', 'enterprise'];
        $plans = Plan::whereIn('key', $planKeys)->get();

        foreach ($plans as $plan) {
            $modules = $plan->modules ?? [];
            if (! is_array($modules)) {
                $modules = json_decode($modules, true) ?? [];
            }
            if (! in_array('broadcasts', $modules, true)) {
                $modules[] = 'broadcasts';
                $plan->modules = array_values(array_unique($modules));
                $plan->save();
                $this->line("  Added broadcasts to plan: {$plan->name} ({$plan->key}).");
            } else {
                $this->line("  Plan {$plan->key} already has broadcasts.");
            }
        }

        $this->info('Done. Campaigns should now appear in the sidebar for accounts on Starter, Pro, or Enterprise.');

        return Command::SUCCESS;
    }
}
