<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'setup_method')) {
                $table->string('setup_method', 20)->default('embedded')->after('business_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_connections', 'setup_method')) {
                $table->dropColumn('setup_method');
            }
        });
    }
};
