<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ChatDesk open-source edition: remove module rows for features that are no longer in the codebase.
 */
return new class extends Migration
{
    private array $keys = ['ai', 'ai.voice', 'whatsapp.calling', 'floaters', 'analytics', 'billing', 'support'];

    public function up(): void
    {
        DB::table('account_modules')->whereIn('module_key', $this->keys)->delete();
        DB::table('modules')->whereIn('key', $this->keys)->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible.
    }
};
