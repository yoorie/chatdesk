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
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
        });

        // Generate slugs for existing connections based on name
        DB::table('whatsapp_connections')
            ->whereNull('slug')
            ->select('id', 'name', 'slug')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (! empty($row->slug)) {
                        continue;
                    }
                    $base = Str::slug($row->name ?? 'connection');
                    if ($base === '') {
                        $base = 'connection';
                    }
                    DB::table('whatsapp_connections')
                        ->where('id', $row->id)
                        ->update(['slug' => $base.'-'.$row->id]);
                }
            });

        // Make slug unique and non-nullable after populating
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_connections', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
