<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'auto_assign_enabled')) {
                $table->boolean('auto_assign_enabled')->default(false)->after('disabled_at');
            }
            if (! Schema::hasColumn('accounts', 'auto_assign_strategy')) {
                $table->string('auto_assign_strategy')->default('round_robin')->after('auto_assign_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'auto_assign_strategy')) {
                $table->dropColumn('auto_assign_strategy');
            }
            if (Schema::hasColumn('accounts', 'auto_assign_enabled')) {
                $table->dropColumn('auto_assign_enabled');
            }
        });
    }
};
