<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->boolean('welcome_message_enabled')->default(false)->after('auto_assign_strategy');
            $table->text('welcome_message_body')->nullable()->after('welcome_message_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['welcome_message_enabled', 'welcome_message_body']);
        });
    }
};
