<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $values = DB::table('platform_settings')->whereIn('key', ['branding.primary_color', 'branding.secondary_color'])->pluck('value', 'key');
        if (strtoupper($values['branding.primary_color'] ?? '') === '#3B82F6'
            && strtoupper($values['branding.secondary_color'] ?? '') === '#8B5CF6') {
            DB::table('platform_settings')->where('key', 'branding.primary_color')->update(['value' => '#00A548']);
            DB::table('platform_settings')->where('key', 'branding.secondary_color')->update(['value' => '#053A35']);
        }
    }

    public function down(): void
    {
        // Keep current branding rather than undoing later user customization.
    }
};
