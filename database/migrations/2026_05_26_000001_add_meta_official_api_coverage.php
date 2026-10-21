<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_meta_event_logs')) {
            Schema::create('whatsapp_meta_event_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('whatsapp_connection_id')->nullable()->constrained('whatsapp_connections')->nullOnDelete();
                $table->string('field')->nullable();
                $table->string('event_type')->nullable();
                $table->string('object_id')->nullable();
                $table->string('status')->nullable();
                $table->text('message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'field']);
                $table->index(['account_id', 'event_type']);
                $table->index(['whatsapp_connection_id', 'received_at'], 'wm_meta_conn_received_idx');
            });
        }

        if (! Schema::hasTable('whatsapp_flows')) {
            Schema::create('whatsapp_flows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained()->cascadeOnDelete();
                $table->foreignId('whatsapp_connection_id')->constrained('whatsapp_connections')->cascadeOnDelete();
                $table->string('meta_flow_id')->nullable();
                $table->string('name');
                $table->string('status')->default('draft');
                $table->string('category')->nullable();
                $table->string('json_version')->nullable();
                $table->string('data_api_version')->nullable();
                $table->string('data_channel_uri', 800)->nullable();
                $table->json('flow_json')->nullable();
                $table->json('validation_errors')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_meta_error')->nullable();
                $table->timestamps();

                $table->unique(['account_id', 'meta_flow_id']);
                $table->index(['account_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
        Schema::dropIfExists('whatsapp_meta_event_logs');
    }
};
