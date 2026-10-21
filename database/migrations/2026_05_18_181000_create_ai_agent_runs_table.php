<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('inbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->foreignId('outbound_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('status', 32);
            $table->string('reason')->nullable();
            $table->text('suggestion')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['ai_agent_id', 'created_at']);
            $table->index(['whatsapp_conversation_id', 'created_at'], 'ai_runs_conv_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_runs');
    }
};
