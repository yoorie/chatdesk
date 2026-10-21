<?php

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Plan::query()
            ->where(function ($query) {
                $query->where('key', 'enterprise')
                    ->orWhereJsonContains('modules', 'ai');
            })
            ->get()
            ->each(function (Plan $plan) {
                $modules = $plan->modules ?? [];

                if (! in_array('ai.voice', $modules, true)) {
                    $modules[] = 'ai.voice';
                    $plan->forceFill(['modules' => array_values($modules)])->save();
                }
            });

        if (! Schema::hasTable('ai_voice_calls')) {
            Schema::create('ai_voice_calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
                $table->string('direction', 20)->default('inbound');
                $table->string('phone_number', 40)->nullable();
                $table->string('contact_name')->nullable();
                $table->string('provider', 40)->nullable();
                $table->string('provider_call_id')->nullable()->index();
                $table->string('status', 40)->default('queued')->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->longText('transcript')->nullable();
                $table->text('summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_voice_calls');

        Plan::query()
            ->whereJsonContains('modules', 'ai.voice')
            ->get()
            ->each(function (Plan $plan) {
                $plan->forceFill([
                    'modules' => array_values(array_filter(
                        $plan->modules ?? [],
                        fn ($module) => $module !== 'ai.voice'
                    )),
                ])->save();
            });

        Module::where('key', 'ai.voice')->delete();
    }
};
