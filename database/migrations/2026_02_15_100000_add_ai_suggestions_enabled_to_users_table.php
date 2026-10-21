<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ai_suggestions_enabled')) {
                $table->boolean('ai_suggestions_enabled')->default(false)->after('notify_sound_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ai_suggestions_enabled')) {
                $table->dropColumn('ai_suggestions_enabled');
            }
        });
    }
};
