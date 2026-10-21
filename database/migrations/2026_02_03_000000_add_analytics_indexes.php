<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(
                ['account_id', 'whatsapp_conversation_id', 'direction', 'created_at'],
                'wa_messages_account_conv_direction_created_idx'
            );
        });

        if (Schema::hasTable('whatsapp_conversation_audit_events')) {
            Schema::table('whatsapp_conversation_audit_events', function (Blueprint $table) {
                $table->index(
                    ['account_id', 'event_type', 'created_at'],
                    'wa_conv_audit_account_event_created_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_messages_account_conv_direction_created_idx');
        });

        if (Schema::hasTable('whatsapp_conversation_audit_events')) {
            Schema::table('whatsapp_conversation_audit_events', function (Blueprint $table) {
                $table->dropIndex('wa_conv_audit_account_event_created_idx');
            });
        }
    }
};
