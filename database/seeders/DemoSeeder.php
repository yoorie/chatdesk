<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo data for previews: modules, the internal plan and a sample organisation with
 * fictional contacts (+1 555 numbers, example.com emails). Run with:
 *   php artisan db:seed --class=DemoSeeder
 * Public demo logins (override with DEMO_* in .env only on a preview server):
 *   Business owner owner@demo.chatdesk.test / demo1234
 *   Agent          agent@demo.chatdesk.test / demo1234
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ModuleSeeder::class, PlanSeeder::class, DemoInboxSeeder::class]);
        self::applyPreviewDefaults();
    }

    /**
     * Preview defaults that `demo:reset` applies again after it restores the
     * previous settings. Nothing extra is needed in the open-source edition.
     */
    public static function applyPreviewDefaults(): void {}
}
