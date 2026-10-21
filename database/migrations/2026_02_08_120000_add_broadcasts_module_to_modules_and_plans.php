<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \App\Models\Module::updateOrCreate(
            ['key' => 'broadcasts'],
            [
                'name' => 'Broadcasts & Campaigns',
                'description' => 'Create and manage WhatsApp broadcast campaigns',
                'is_core' => false,
                'is_enabled' => true,
            ]
        );

        $planKeys = ['starter', 'pro', 'enterprise'];
        $plans = \App\Models\Plan::whereIn('key', $planKeys)->get();

        foreach ($plans as $plan) {
            $modules = $plan->modules ?? [];
            if (! is_array($modules)) {
                $modules = json_decode($modules, true) ?? [];
            }

            if (! in_array('broadcasts', $modules, true)) {
                $modules[] = 'broadcasts';
                $plan->modules = array_values(array_unique($modules));
                $plan->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('key', 'broadcasts')->first();
        if ($module) {
            $module->delete();
        }

        $planKeys = ['starter', 'pro', 'enterprise'];
        $plans = \App\Models\Plan::whereIn('key', $planKeys)->get();

        foreach ($plans as $plan) {
            $modules = $plan->modules ?? [];
            if (! is_array($modules)) {
                $modules = json_decode($modules, true) ?? [];
            }

            $modules = array_values(array_filter($modules, fn ($m) => $m !== 'broadcasts'));
            $plan->modules = $modules;
            $plan->save();
        }
    }
};
