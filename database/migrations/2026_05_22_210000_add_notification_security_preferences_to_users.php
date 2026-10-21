<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'notify_billing_enabled',
                'notify_waba_enabled',
                'notify_automation_enabled',
                'notify_leads_enabled',
                'notify_templates_enabled',
                'notify_email_enabled',
                'notify_in_app_enabled',
            ] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $table->boolean($column)->default(true)->after('notify_sound_enabled');
                }
            }

            if (! Schema::hasColumn('users', 'quiet_hours_enabled')) {
                $table->boolean('quiet_hours_enabled')->default(false)->after('notify_in_app_enabled');
            }
            if (! Schema::hasColumn('users', 'quiet_hours_start')) {
                $table->string('quiet_hours_start', 5)->nullable()->after('quiet_hours_enabled');
            }
            if (! Schema::hasColumn('users', 'quiet_hours_end')) {
                $table->string('quiet_hours_end', 5)->nullable()->after('quiet_hours_start');
            }
            if (! Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'two_factor_enabled_at')) {
                $table->timestamp('two_factor_enabled_at')->nullable()->after('two_factor_secret');
            }
            if (! Schema::hasColumn('users', 'force_password_reset_at')) {
                $table->timestamp('force_password_reset_at')->nullable()->after('two_factor_enabled_at');
            }
            if (! Schema::hasColumn('users', 'sessions_revoked_at')) {
                $table->timestamp('sessions_revoked_at')->nullable()->after('force_password_reset_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'sessions_revoked_at',
                'force_password_reset_at',
                'two_factor_enabled_at',
                'two_factor_secret',
                'quiet_hours_end',
                'quiet_hours_start',
                'quiet_hours_enabled',
                'notify_in_app_enabled',
                'notify_email_enabled',
                'notify_templates_enabled',
                'notify_leads_enabled',
                'notify_automation_enabled',
                'notify_waba_enabled',
                'notify_billing_enabled',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
