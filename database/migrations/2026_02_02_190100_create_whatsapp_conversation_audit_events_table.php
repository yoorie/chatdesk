<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversation_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_conversation_id')
                ->constrained('whatsapp_conversations')
                ->onDelete('cascade')
                ->name('wa_conv_audit_conv_id_fk');
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('wa_conv_audit_actor_id_fk');
            $table->string('event_type');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'id'], 'wa_conv_audit_conv_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_audit_events');
    }
};
