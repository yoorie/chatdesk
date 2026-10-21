<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'meta_verified_name')) {
                $table->string('meta_verified_name')->nullable()->after('business_phone');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'phone_number_status')) {
                $table->string('phone_number_status', 80)->nullable()->after('meta_verified_name');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'quality_rating')) {
                $table->string('quality_rating', 80)->nullable()->after('phone_number_status');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'code_verification_status')) {
                $table->string('code_verification_status', 80)->nullable()->after('quality_rating');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            foreach ([
                'code_verification_status',
                'quality_rating',
                'phone_number_status',
                'meta_verified_name',
            ] as $column) {
                if (Schema::hasColumn('whatsapp_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
