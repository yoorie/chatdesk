<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_conversation_audit_events')) {
            Schema::table('whatsapp_conversation_audit_events', function (Blueprint $table) {
                $table->index(
                    ['whatsapp_conversation_id', 'created_at'],
                    'wa_conv_audit_conv_created_at_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('whatsapp_conversation_audit_events')) {
            Schema::table('whatsapp_conversation_audit_events', function (Blueprint $table) {
                $table->dropIndex('wa_conv_audit_conv_created_at_idx');
            });
        }
    }
};
