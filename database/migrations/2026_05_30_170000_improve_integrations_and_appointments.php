<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('account_appointments', 'external_source')) {
                $table->string('external_source')->nullable()->after('account_id');
            }
            if (! Schema::hasColumn('account_appointments', 'external_id')) {
                $table->string('external_id')->nullable()->after('external_source');
            }
            if (! Schema::hasColumn('account_appointments', 'location')) {
                $table->string('location')->nullable()->after('type');
            }
            if (! Schema::hasColumn('account_appointments', 'meeting_url')) {
                $table->string('meeting_url', 800)->nullable()->after('location');
            }
            if (! Schema::hasColumn('account_appointments', 'description')) {
                $table->text('description')->nullable()->after('meeting_url');
            }
            if (! Schema::hasColumn('account_appointments', 'reminder_minutes_before')) {
                $table->unsignedInteger('reminder_minutes_before')->default(60)->after('reminder_enabled');
            }
            if (! Schema::hasColumn('account_appointments', 'metadata')) {
                $table->json('metadata')->nullable()->after('reminder_sent_at');
            }
        });

        Schema::table('account_appointments', function (Blueprint $table) {
            $table->index(['account_id', 'external_source', 'external_id'], 'account_appts_external_idx');
        });
    }

    public function down(): void
    {
        Schema::table('account_appointments', function (Blueprint $table) {
            $table->dropIndex('account_appts_external_idx');
        });

        Schema::table('account_appointments', function (Blueprint $table) {
            foreach (['external_source', 'external_id', 'location', 'meeting_url', 'description', 'reminder_minutes_before', 'metadata'] as $column) {
                if (Schema::hasColumn('account_appointments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
