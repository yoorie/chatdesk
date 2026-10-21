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
        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_contact_id')->nullable()->constrained('whatsapp_contacts')->onDelete('set null');

            // Recipient info (can be contact or custom)
            $table->string('phone_number'); // WhatsApp phone number (with country code)
            $table->string('name')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'sending', 'sent', 'delivered', 'read', 'failed', 'skipped'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Message tracking
            $table->string('message_id')->nullable(); // WhatsApp message ID
            $table->string('wamid')->nullable(); // WhatsApp message ID from API

            // Custom parameters for template messages
            $table->json('template_params')->nullable();

            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('phone_number');
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
