<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_call_voice_sessions')) {
            return;
        }

        Schema::create('whatsapp_call_voice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_call_id')->constrained('ai_voice_calls')->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('direction', 20)->default('outbound');
            $table->string('status', 40)->default('queued')->index();
            $table->string('worker_id')->nullable()->index();
            $table->text('local_sdp')->nullable();
            $table->text('remote_sdp')->nullable();
            $table->json('transcript')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_call_voice_sessions');
    }
};
