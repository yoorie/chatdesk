<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'notify_assignment_enabled')) {
                $table->boolean('notify_assignment_enabled')->default(true)->after('is_platform_admin');
            }
            if (! Schema::hasColumn('users', 'notify_mention_enabled')) {
                $table->boolean('notify_mention_enabled')->default(true)->after('notify_assignment_enabled');
            }
            if (! Schema::hasColumn('users', 'notify_sound_enabled')) {
                $table->boolean('notify_sound_enabled')->default(true)->after('notify_mention_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'notify_sound_enabled')) {
                $table->dropColumn('notify_sound_enabled');
            }
            if (Schema::hasColumn('users', 'notify_mention_enabled')) {
                $table->dropColumn('notify_mention_enabled');
            }
            if (Schema::hasColumn('users', 'notify_assignment_enabled')) {
                $table->dropColumn('notify_assignment_enabled');
            }
        });
    }
};
