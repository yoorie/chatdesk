<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'business_address')) {
                $table->string('business_address')->nullable()->after('business_about');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'business_description')) {
                $table->string('business_description', 512)->nullable()->after('business_address');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'business_email')) {
                $table->string('business_email')->nullable()->after('business_description');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'business_websites')) {
                $table->json('business_websites')->nullable()->after('business_email');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'business_vertical')) {
                $table->string('business_vertical', 80)->nullable()->after('business_websites');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'profile_picture_url')) {
                $table->text('profile_picture_url')->nullable()->after('business_vertical');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'profile_picture_handle')) {
                $table->text('profile_picture_handle')->nullable()->after('profile_picture_url');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'profile_synced_at')) {
                $table->timestamp('profile_synced_at')->nullable()->after('profile_picture_handle');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'profile_sync_error')) {
                $table->text('profile_sync_error')->nullable()->after('profile_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            foreach ([
                'profile_sync_error',
                'profile_synced_at',
                'profile_picture_handle',
                'profile_picture_url',
                'business_vertical',
                'business_websites',
                'business_email',
                'business_description',
                'business_address',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
