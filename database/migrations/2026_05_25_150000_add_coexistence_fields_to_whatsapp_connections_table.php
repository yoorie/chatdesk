<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'connection_mode')) {
                $table->string('connection_mode', 40)->default('cloud_api')->after('setup_method');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'coexistence_status')) {
                $table->string('coexistence_status', 40)->nullable()->after('connection_mode');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'coexistence_metadata')) {
                $table->json('coexistence_metadata')->nullable()->after('coexistence_status');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'coexistence_last_checked_at')) {
                $table->timestamp('coexistence_last_checked_at')->nullable()->after('coexistence_metadata');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'coexistence_last_error')) {
                $table->text('coexistence_last_error')->nullable()->after('coexistence_last_checked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            foreach ([
                'coexistence_last_error',
                'coexistence_last_checked_at',
                'coexistence_metadata',
                'coexistence_status',
                'connection_mode',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
