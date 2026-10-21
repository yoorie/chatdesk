<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'auto_close_conversations_enabled')) {
                $table->boolean('auto_close_conversations_enabled')->default(false)->after('welcome_message_body');
            }

            if (! Schema::hasColumn('accounts', 'auto_close_after_hours')) {
                $table->unsignedSmallInteger('auto_close_after_hours')->default(48)->after('auto_close_conversations_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            foreach (['auto_close_conversations_enabled', 'auto_close_after_hours'] as $column) {
                if (Schema::hasColumn('accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
