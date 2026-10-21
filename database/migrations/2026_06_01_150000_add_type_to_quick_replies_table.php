<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quick_replies') || Schema::hasColumn('quick_replies', 'type')) {
            return;
        }

        Schema::table('quick_replies', function (Blueprint $table) {
            $table->string('type', 20)->default('reply')->after('account_id')->index();
        });

        DB::table('quick_replies')
            ->whereColumn('label', 'message')
            ->whereRaw('LENGTH(label) <= 20')
            ->update(['type' => 'button']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('quick_replies') || ! Schema::hasColumn('quick_replies', 'type')) {
            return;
        }

        Schema::table('quick_replies', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
