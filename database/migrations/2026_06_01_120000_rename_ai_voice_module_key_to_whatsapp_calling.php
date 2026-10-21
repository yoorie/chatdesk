<?php

use App\Models\AccountModule;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Module::updateOrCreate(
            ['key' => 'whatsapp.calling'],
            [
                'name' => 'WhatsApp Calling',
                'description' => 'Enable, diagnose, and route WhatsApp Business calls',
                'is_core' => false,
                'is_enabled' => true,
            ]
        );

        Module::where('key', 'ai.voice')->delete();

        AccountModule::where('module_key', 'ai.voice')->update([
            'module_key' => 'whatsapp.calling',
        ]);

        Plan::query()->get()->each(function (Plan $plan) {
            $modules = collect($plan->modules ?? [])
                ->map(fn ($module) => $module === 'ai.voice' ? 'whatsapp.calling' : $module)
                ->unique()
                ->values()
                ->all();

            if ($modules !== ($plan->modules ?? [])) {
                $plan->forceFill(['modules' => $modules])->save();
            }
        });
    }

    public function down(): void
    {
        Module::updateOrCreate(
            ['key' => 'ai.voice'],
            [
                'name' => 'WhatsApp AI Calling',
                'description' => 'AI-assisted WhatsApp Business calling workflows',
                'is_core' => false,
                'is_enabled' => true,
            ]
        );

        AccountModule::where('module_key', 'whatsapp.calling')->update([
            'module_key' => 'ai.voice',
        ]);

        Plan::query()->get()->each(function (Plan $plan) {
            $modules = collect($plan->modules ?? [])
                ->map(fn ($module) => $module === 'whatsapp.calling' ? 'ai.voice' : $module)
                ->unique()
                ->values()
                ->all();

            if ($modules !== ($plan->modules ?? [])) {
                $plan->forceFill(['modules' => $modules])->save();
            }
        });

        Module::where('key', 'whatsapp.calling')->delete();
    }
};
