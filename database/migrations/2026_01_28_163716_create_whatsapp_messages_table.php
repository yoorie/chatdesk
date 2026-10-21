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
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_conversation_id')->constrained()->onDelete('cascade');
            $table->string('direction'); // inbound/outbound
            $table->string('meta_message_id')->nullable(); // id from Meta
            $table->string('type'); // text/image/audio/video/document/location/interactive/template
            $table->longText('text_body')->nullable();
            $table->json('payload')->nullable(); // raw webhook or send payload
            $table->string('status')->default('queued'); // queued/sent/delivered/read/failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'meta_message_id'], 'unique_meta_message_id');
            $table->index(
                ['account_id', 'whatsapp_conversation_id', 'created_at'],
                'wm_ws_conv_created_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
