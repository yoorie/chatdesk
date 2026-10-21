<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bot_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_id')->constrained()->onDelete('cascade');
            $table->foreignId('bot_flow_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_conversation_id')->nullable()->constrained('whatsapp_conversations')->onDelete('set null');
            $table->string('trigger_event_id'); // unique id per inbound message (meta_message_id)
            $table->string('status'); // running|success|failed|skipped
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('logs')->nullable(); // structured log, capped
            $table->timestamps();

            // Idempotency guard
            $table->unique(['account_id', 'trigger_event_id', 'bot_flow_id'], 'unique_execution');
            $table->index(['account_id', 'bot_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_executions');
    }
};
