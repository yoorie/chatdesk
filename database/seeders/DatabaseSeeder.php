<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([ModuleSeeder::class]);

        // A command-line install (`php artisan migrate --seed`) is complete at this point,
        // so mark it installed and skip the web installer in public/install/.
        $lock = storage_path('installed');
        if (! file_exists($lock)) {
            @file_put_contents($lock, 'installed via artisan at '.now()->toIso8601String().PHP_EOL);
        }
    }
}
