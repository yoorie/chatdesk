<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_conversations', 'assigned_to')) {
            return;
        }

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->index(['account_id', 'assigned_to'], 'whatsapp_conversations_account_id_assigned_to_index');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropIndex('whatsapp_conversations_account_id_assigned_to_index');
        });
    }
};
