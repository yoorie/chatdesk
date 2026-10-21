<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')
            ->whereIn('key', ['branding.platform_name', 'general.platform_name', 'mail.from_name'])
            ->whereIn('value', ['Laravel', 'WACP'])
            ->update(['value' => config('app.name'), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Preserve the current platform name and any later custom branding.
    }
};
