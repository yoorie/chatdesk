<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_access_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('mobile_access_tokens', 'device_platform')) {
                $table->string('device_platform', 40)->nullable()->after('name');
            }
            if (! Schema::hasColumn('mobile_access_tokens', 'device_id')) {
                $table->string('device_id', 160)->nullable()->after('device_platform');
            }
            if (! Schema::hasColumn('mobile_access_tokens', 'push_token')) {
                $table->text('push_token')->nullable()->after('device_id');
            }
            if (! Schema::hasColumn('mobile_access_tokens', 'push_provider')) {
                $table->string('push_provider', 40)->nullable()->after('push_token');
            }
            if (! Schema::hasColumn('mobile_access_tokens', 'push_token_updated_at')) {
                $table->timestamp('push_token_updated_at')->nullable()->after('push_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mobile_access_tokens', function (Blueprint $table) {
            foreach (['push_token_updated_at', 'push_provider', 'push_token', 'device_id', 'device_platform'] as $column) {
                if (Schema::hasColumn('mobile_access_tokens', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
