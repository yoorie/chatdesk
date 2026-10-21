<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'meta_business_id')) {
                $table->string('meta_business_id')->nullable()->after('waba_id');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'meta_waba_name')) {
                $table->string('meta_waba_name')->nullable()->after('meta_business_id');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'meta_account_review_status')) {
                $table->string('meta_account_review_status')->nullable()->after('meta_waba_name');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'meta_business_verification_status')) {
                $table->string('meta_business_verification_status')->nullable()->after('meta_account_review_status');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'meta_timezone_id')) {
                $table->string('meta_timezone_id')->nullable()->after('meta_business_verification_status');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'meta_template_namespace')) {
                $table->string('meta_template_namespace')->nullable()->after('meta_timezone_id');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'meta_subscribed_apps')) {
                $table->json('meta_subscribed_apps')->nullable()->after('meta_template_namespace');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            foreach ([
                'meta_subscribed_apps',
                'meta_template_namespace',
                'meta_timezone_id',
                'meta_business_verification_status',
                'meta_account_review_status',
                'meta_waba_name',
                'meta_business_id',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
