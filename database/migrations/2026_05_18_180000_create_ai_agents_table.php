<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('avatar', 12)->nullable();
            $table->string('role')->default('support');
            $table->string('language', 24)->default('en');
            $table->string('tone', 32)->default('professional');
            $table->string('mode', 32)->default('suggest');
            $table->boolean('is_active')->default(true);
            $table->text('instructions')->nullable();
            $table->json('knowledge_sources')->nullable();
            $table->json('guardrails')->nullable();
            $table->json('escalation_rules')->nullable();
            $table->json('working_hours')->nullable();
            $table->unsignedTinyInteger('max_auto_replies_per_conversation')->default(3);
            $table->decimal('confidence_threshold', 3, 2)->default(0.70);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['account_id', 'slug']);
            $table->index(['account_id', 'is_active']);
            $table->index(['account_id', 'mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
