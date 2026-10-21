<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add slug column as nullable first
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
        });

        // Generate slugs for existing contacts based on wa_id or name
        DB::table('whatsapp_contacts')
            ->whereNull('slug')
            ->select('id', 'wa_id', 'name', 'slug')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (! empty($row->slug)) {
                        continue;
                    }
                    $baseValue = $row->wa_id ?? $row->name ?? 'contact';
                    $base = Str::slug($baseValue);
                    if ($base === '') {
                        $base = 'contact';
                    }
                    DB::table('whatsapp_contacts')
                        ->where('id', $row->id)
                        ->update(['slug' => $base.'-'.$row->id]);
                }
            });

        // Make slug unique and non-nullable after populating
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
