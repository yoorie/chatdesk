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
            ['key' => 'contacts'],
            [
                'name' => 'Contacts & CRM',
                'description' => 'Manage contacts, tags, segments, and customer relationships',
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

            if (! in_array('contacts', $modules, true)) {
                $modules[] = 'contacts';
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
        $module = \App\Models\Module::where('key', 'contacts')->first();
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

            $modules = array_values(array_filter($modules, fn ($key) => $key !== 'contacts'));
            $plan->modules = $modules;
            $plan->save();
        }
    }
};
