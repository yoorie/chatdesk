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
        Schema::create('campaign_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_recipient_id')->constrained('campaign_recipients')->onDelete('cascade');
            $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->onDelete('set null');

            // Message details
            $table->string('wamid')->nullable(); // WhatsApp message ID from API
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->text('error_message')->nullable();

            // Webhook tracking
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('wamid');
            $table->unique('campaign_recipient_id'); // One message per recipient
            // Note: wamid is NOT unique as same wamid could exist in different contexts
            // But we index it for fast lookups in status updates
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
    }
};
