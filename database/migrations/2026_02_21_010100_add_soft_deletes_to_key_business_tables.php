<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_contacts', 'purge_after_at')) {
                $table->timestamp('purge_after_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('whatsapp_contacts', 'deleted_at')) {
                $table->softDeletes()->after('purge_after_at');
            }
            $table->index('purge_after_at');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'purge_after_at')) {
                $table->timestamp('purge_after_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->softDeletes()->after('purge_after_at');
            }
            $table->index('purge_after_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_contacts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('whatsapp_contacts', 'purge_after_at')) {
                $table->dropColumn('purge_after_at');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('campaigns', 'purge_after_at')) {
                $table->dropColumn('purge_after_at');
            }
        });
    }
};
