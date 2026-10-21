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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_connection_id')->nullable()->constrained('whatsapp_connections')->onDelete('set null');
            $table->foreignId('whatsapp_template_id')->nullable()->constrained('whatsapp_templates')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'sending', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->enum('type', ['template', 'text', 'media'])->default('template');

            // Template message data
            $table->json('template_params')->nullable(); // Parameters for template messages
            $table->text('message_text')->nullable(); // For text messages
            $table->string('media_url')->nullable(); // For media messages
            $table->string('media_type')->nullable(); // image, video, document, audio

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Recipient selection
            $table->enum('recipient_type', ['contacts', 'custom', 'segment'])->default('contacts');
            $table->json('recipient_filters')->nullable(); // Filters for contact selection
            $table->json('custom_recipients')->nullable(); // Custom phone numbers

            // Statistics
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('read_count')->default(0);
            $table->integer('failed_count')->default(0);

            // Settings
            $table->integer('send_delay_seconds')->default(0); // Delay between messages
            $table->boolean('respect_opt_out')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'scheduled_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
