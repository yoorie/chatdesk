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
            ['key' => 'contacts'],
            [
                'name' => 'Contacts & CRM',
                'description' => 'Manage contacts, tags, segments, and customer relationships',
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
        $module = \App\Models\Module::where('key', 'contacts')->first();
        if ($module) {
            $module->delete();
        }
    }
};
