<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Run even when DEMO_MODE is off}';

    protected $description = 'Wipe the database and reload demo data (demo sites only)';

    public function handle(): int
    {
        if (! config('demo.enabled') && ! $this->option('force')) {
            $this->warn('DEMO_MODE is off. Nothing done.');

            return self::SUCCESS;
        }

        // Keep installer-written platform settings (branding, cron token) across resets.
        $keep = \Illuminate\Support\Facades\DB::table('platform_settings')->get()->map(fn ($r) => (array) $r)->all();

        $this->call('migrate:fresh', ['--force' => true]);
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\DemoSeeder', '--force' => true]);

        foreach (array_chunk($keep, 100) as $chunk) {
            \Illuminate\Support\Facades\DB::table('platform_settings')->upsert($chunk, ['key']);
        }

        // The restore puts the previous platform settings back, including an
        // AI switch that was off and a landing page with the logo row on.
        // Re-apply the preview defaults after that restore.
        \Database\Seeders\DemoSeeder::applyPreviewDefaults();

        $this->info('Demo data reset.');

        return self::SUCCESS;
    }
}
