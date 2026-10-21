<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \App\Models\Module::updateOrCreate(
            ['key' => 'broadcasts'],
            [
                'name' => 'Broadcasts & Campaigns',
                'description' => 'Create and manage WhatsApp broadcast campaigns',
                'is_core' => false,
                'is_enabled' => true,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = \App\Models\Module::where('key', 'broadcasts')->first();
        if ($module) {
            $module->delete();
        }
    }
};
