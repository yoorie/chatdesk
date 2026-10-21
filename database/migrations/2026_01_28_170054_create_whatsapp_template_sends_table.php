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
        Schema::create('whatsapp_template_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_message_id')->constrained()->onDelete('cascade');
            $table->string('to_wa_id');
            $table->json('variables')->nullable();
            $table->string('status'); // queued/sent/failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'whatsapp_template_id']);
            $table->index(['account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_template_sends');
    }
};
