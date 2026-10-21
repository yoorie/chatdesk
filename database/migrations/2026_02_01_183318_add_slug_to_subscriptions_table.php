<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Check if slug column already exists (from failed migration)
        if (! Schema::hasColumn('subscriptions', 'slug')) {
            // Add slug column as nullable first
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('slug')->nullable()->after('id');
            });
        }

        // Generate slugs for existing subscriptions
        DB::table('subscriptions')
            ->whereNull('slug')
            ->select('id', 'slug')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    if (! empty($row->slug)) {
                        continue;
                    }
                    DB::table('subscriptions')
                        ->where('id', $row->id)
                        ->update(['slug' => 'sub-'.$row->id]);
                }
            });

        // Drop any existing slug index before enforcing unique/non-null
        $this->dropSlugIndexes($driver);

        // Make slug unique and non-nullable after populating
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        $this->dropSlugIndexes($driver);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }

    private function dropSlugIndexes(string $driver): void
    {
        if ($driver === 'sqlite') {
            $indexes = DB::select("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='subscriptions' AND name LIKE '%slug%'");
            foreach ($indexes as $index) {
                DB::statement("DROP INDEX IF EXISTS {$index->name}");
            }

            return;
        }

        if ($driver === 'mysql') {
            $indexes = DB::select(
                "SELECT index_name AS name
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = 'subscriptions'
                   AND index_name LIKE '%slug%'"
            );
            foreach ($indexes as $index) {
                DB::statement("ALTER TABLE subscriptions DROP INDEX {$index->name}");
            }

            return;
        }

        // Fallback: try to drop the conventional unique index name if it exists.
        try {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        } catch (\Throwable $e) {
        }
    }
};
