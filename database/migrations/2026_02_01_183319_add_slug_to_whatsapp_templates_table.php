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
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('id');
        });

        // Generate slugs for existing templates based on name + connection
        DB::table('whatsapp_templates')
            ->whereNull('slug')
            ->select('id', 'name', 'whatsapp_connection_id', 'slug')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (! empty($row->slug)) {
                        continue;
                    }
                    $base = Str::slug($row->name ?? 'template');
                    if ($base === '') {
                        $base = 'template';
                    }
                    $connectionPart = $row->whatsapp_connection_id ?? 'conn';
                    DB::table('whatsapp_templates')
                        ->where('id', $row->id)
                        ->update(['slug' => $base.'-'.$connectionPart.'-'.$row->id]);
                }
            });

        // Make slug unique and non-nullable after populating
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
