<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestion_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('whatsapp_conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->text('suggestion');
            $table->enum('verdict', ['up', 'down']);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['whatsapp_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestion_feedback');
    }
};
