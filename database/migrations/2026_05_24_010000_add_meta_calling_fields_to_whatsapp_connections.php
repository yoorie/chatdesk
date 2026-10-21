<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'calling_status')) {
                $table->string('calling_status', 40)->default('unknown')->after('webhook_subscribed');
            }
            if (! Schema::hasColumn('whatsapp_connections', 'calling_enabled')) {
                $table->boolean('calling_enabled')->default(false)->after('calling_status');
            }
            if (! Schema::hasColumn('whatsapp_connections', 'calling_webhook_subscribed')) {
                $table->boolean('calling_webhook_subscribed')->default(false)->after('calling_enabled');
            }
            if (! Schema::hasColumn('whatsapp_connections', 'calling_settings')) {
                $table->json('calling_settings')->nullable()->after('calling_webhook_subscribed');
            }
            if (! Schema::hasColumn('whatsapp_connections', 'calling_last_checked_at')) {
                $table->timestamp('calling_last_checked_at')->nullable()->after('calling_settings');
            }
            if (! Schema::hasColumn('whatsapp_connections', 'calling_last_error')) {
                $table->text('calling_last_error')->nullable()->after('calling_last_checked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            foreach ([
                'calling_last_error',
                'calling_last_checked_at',
                'calling_settings',
                'calling_webhook_subscribed',
                'calling_enabled',
                'calling_status',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
