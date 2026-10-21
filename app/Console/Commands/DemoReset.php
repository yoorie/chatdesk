<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

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

        User::updateOrCreate(['email' => config('demo.admin_email')], [
            'name' => 'Super Admin',
            'password' => Hash::make((string) config('demo.admin_password')),
            'is_platform_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->info('Demo data reset.');

        return self::SUCCESS;
    }
}
