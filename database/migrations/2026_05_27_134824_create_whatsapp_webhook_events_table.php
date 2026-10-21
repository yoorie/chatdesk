<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_webhook_events')) {
            return;
        }

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
            $table->string('event_key', 191);
            $table->string('event_type')->nullable();
            $table->string('status')->default('processing');
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('first_received_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'event_key'], 'wa_webhook_events_account_event_unique');
            $table->index(['whatsapp_connection_id', 'status'], 'wa_webhook_events_conn_status_idx');
            $table->index(['account_id', 'last_received_at'], 'wa_webhook_events_account_received_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
