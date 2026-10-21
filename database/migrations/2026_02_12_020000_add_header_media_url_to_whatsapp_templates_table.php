<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_templates', 'header_media_url')) {
            Schema::table('whatsapp_templates', function (Blueprint $table): void {
                $table->text('header_media_url')->nullable()->after('header_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_templates', 'header_media_url')) {
            Schema::table('whatsapp_templates', function (Blueprint $table): void {
                $table->dropColumn('header_media_url');
            });
        }
    }
};
