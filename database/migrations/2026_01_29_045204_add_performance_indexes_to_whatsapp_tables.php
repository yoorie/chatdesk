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
        // Add indexes with try-catch to handle if they already exist
        try {
            Schema::table('whatsapp_conversations', function (Blueprint $table) {
                // Add index for status filtering
                $table->index(['account_id', 'status'], 'whatsapp_conversations_account_id_status_index');
                // Add index for connection filtering
                $table->index(['account_id', 'whatsapp_connection_id'], 'whatsapp_conversations_account_id_connection_id_index');
            });
        } catch (\Exception $e) {
            // Indexes may already exist, continue
        }

        try {
            Schema::table('whatsapp_messages', function (Blueprint $table) {
                // Add index for direction filtering
                $table->index(['whatsapp_conversation_id', 'direction'], 'whatsapp_messages_conversation_id_direction_index');
                // Add index for status updates
                $table->index(['account_id', 'status'], 'whatsapp_messages_account_id_status_index');
            });
        } catch (\Exception $e) {
            // Indexes may already exist, continue
        }

        try {
            Schema::table('whatsapp_templates', function (Blueprint $table) {
                // Add indexes for template search
                $table->index(['account_id', 'status'], 'whatsapp_templates_account_id_status_index');
                $table->index(['account_id', 'name'], 'whatsapp_templates_account_id_name_index');
            });
        } catch (\Exception $e) {
            // Indexes may already exist, continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('whatsapp_conversations_account_id_status_index');
            $table->dropIndex('whatsapp_conversations_account_id_connection_id_index');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('whatsapp_messages_conversation_id_direction_index');
            $table->dropIndex('whatsapp_messages_account_id_status_index');
        });

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropIndex('whatsapp_templates_account_id_status_index');
            $table->dropIndex('whatsapp_templates_account_id_name_index');
        });
    }
};
