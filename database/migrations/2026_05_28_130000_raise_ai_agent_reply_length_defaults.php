<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_agents') || ! Schema::hasColumn('ai_agents', 'max_reply_chars')) {
            return;
        }

        DB::table('ai_agents')
            ->where(function ($query) {
                $query->whereNull('max_reply_chars')
                    ->orWhere('max_reply_chars', '<', 2500);
            })
            ->update(['max_reply_chars' => 3500]);
    }

    public function down(): void
    {
        // Keep existing agent configuration intact.
    }
};
