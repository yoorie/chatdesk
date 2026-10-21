<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_conversations', 'priority')) {
                $table->string('priority')->default('normal')->after('status');
                $table->index(['account_id', 'priority'], 'whatsapp_conversations_account_id_priority_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_conversations', 'priority')) {
                $table->dropIndex('whatsapp_conversations_account_id_priority_index');
                $table->dropColumn('priority');
            }
        });
    }
};
