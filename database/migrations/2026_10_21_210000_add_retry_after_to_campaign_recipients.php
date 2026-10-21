<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_recipients', 'retry_after')) {
                $table->timestamp('retry_after')->nullable();
            }
            if (! Schema::hasColumn('campaign_recipients', 'attempt_count')) {
                $table->unsignedSmallInteger('attempt_count')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('campaign_recipients', 'attempt_count')) {
                $table->dropColumn('attempt_count');
            }
            if (Schema::hasColumn('campaign_recipients', 'retry_after')) {
                $table->dropColumn('retry_after');
            }
        });
    }
};
