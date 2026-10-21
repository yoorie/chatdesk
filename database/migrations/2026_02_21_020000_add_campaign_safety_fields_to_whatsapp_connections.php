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
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_connections', 'throughput_cap_per_minute')) {
                $table->unsignedInteger('throughput_cap_per_minute')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('whatsapp_connections', 'quiet_hours_start')) {
                $table->string('quiet_hours_start', 5)->nullable()->after('throughput_cap_per_minute'); // HH:MM
            }
            if (! Schema::hasColumn('whatsapp_connections', 'quiet_hours_end')) {
                $table->string('quiet_hours_end', 5)->nullable()->after('quiet_hours_start'); // HH:MM
            }
            if (! Schema::hasColumn('whatsapp_connections', 'quiet_hours_timezone')) {
                $table->string('quiet_hours_timezone', 64)->nullable()->after('quiet_hours_end');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            foreach (['throughput_cap_per_minute', 'quiet_hours_start', 'quiet_hours_end', 'quiet_hours_timezone'] as $column) {
                if (Schema::hasColumn('whatsapp_connections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
