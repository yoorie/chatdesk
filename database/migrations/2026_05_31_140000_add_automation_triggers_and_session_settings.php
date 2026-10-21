<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_surveys', function (Blueprint $table) {
            if (! Schema::hasColumn('account_surveys', 'automation_enabled')) {
                $table->boolean('automation_enabled')->default(false)->after('success_message');
            }
            if (! Schema::hasColumn('account_surveys', 'automation_bot_flow_id')) {
                $table->foreignId('automation_bot_flow_id')->nullable()->after('automation_enabled')->constrained('bot_flows')->nullOnDelete();
            }
        });

        Schema::table('bots', function (Blueprint $table) {
            if (! Schema::hasColumn('bots', 'session_timeout_minutes')) {
                $table->unsignedInteger('session_timeout_minutes')->default(1440)->after('stop_on_first_flow');
            }
            if (! Schema::hasColumn('bots', 'session_resume_mode')) {
                $table->string('session_resume_mode', 40)->default('resume')->after('session_timeout_minutes');
            }
            if (! Schema::hasColumn('bots', 'session_expired_message')) {
                $table->text('session_expired_message')->nullable()->after('session_resume_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_surveys', function (Blueprint $table) {
            if (Schema::hasColumn('account_surveys', 'automation_bot_flow_id')) {
                $table->dropConstrainedForeignId('automation_bot_flow_id');
            }
            if (Schema::hasColumn('account_surveys', 'automation_enabled')) {
                $table->dropColumn('automation_enabled');
            }
        });

        Schema::table('bots', function (Blueprint $table) {
            foreach (['session_expired_message', 'session_resume_mode', 'session_timeout_minutes'] as $column) {
                if (Schema::hasColumn('bots', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
