<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'business_category')) {
                $table->string('business_category')->nullable()->after('business_phone');
            }

            if (! Schema::hasColumn('whatsapp_connections', 'business_about')) {
                $table->string('business_about', 512)->nullable()->after('business_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_connections', 'business_about')) {
                $table->dropColumn('business_about');
            }

            if (Schema::hasColumn('whatsapp_connections', 'business_category')) {
                $table->dropColumn('business_category');
            }
        });
    }
};
