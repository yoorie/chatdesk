<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_conversation_id')
                ->constrained('whatsapp_conversations')
                ->onDelete('cascade')
                ->name('wa_conv_notes_conv_id_fk');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->name('wa_conv_notes_created_by_fk');
            $table->text('note');
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'id'], 'wa_conv_notes_conv_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_notes');
    }
};
