<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['account_id', 'created_at'], 'wm_account_created_idx');
            $table->index(['account_id', 'direction', 'read_at'], 'wm_account_unread_idx');
            $table->index('created_at', 'wm_created_idx');
        });
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->index(['scope', 'read_at', 'severity'], 'notifications_scope_unread_idx');
            $table->index(['scope', 'created_at'], 'notifications_scope_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wm_account_created_idx');
            $table->dropIndex('wm_account_unread_idx');
            $table->dropIndex('wm_created_idx');
        });
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_scope_unread_idx');
            $table->dropIndex('notifications_scope_recent_idx');
        });
    }
};
