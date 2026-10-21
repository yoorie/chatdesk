<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Demo data for previews: modules, plans and a sample workspace with
 * fictional contacts (+1 555 numbers, example.com emails). Run with:
 *   php artisan db:seed --class=DemoSeeder
 * Public demo logins (override with DEMO_* in .env only on a preview server):
 *   Super Admin    admin@demo.chatdesk.test / demo1234
 *   Business owner owner@demo.chatdesk.test / demo1234
 *   Agent          agent@demo.chatdesk.test / demo1234
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([ModuleSeeder::class, PlanSeeder::class, CmsSeeder::class, DemoInboxSeeder::class]);
        self::applyPreviewDefaults();
    }

    /**
     * Preview defaults that `demo:reset` applies again after it restores the
     * previous platform settings. AI is switched on for the demo plan and
     * workspace. Demo mode still refuses real provider calls. The landing
     * logo row (WhatsApp, Meta, Sheets) stays off.
     */
    public static function applyPreviewDefaults(): void
    {
        \App\Models\PlatformSetting::set('ai.enabled', true, 'boolean', 'ai');

        $plan = \App\Models\Plan::query()->where('key', 'pro')->first();
        if ($plan && ! in_array('ai', $plan->modules ?? [], true)) {
            $modules = array_values(array_unique([...(array) $plan->modules, 'ai']));
            $plan->forceFill(['modules' => $modules])->save();
        }

        $owner = \App\Models\User::query()->where('email', config('demo.user_email'))->first();
        $account = $owner?->ownedAccounts()->first();
        if ($account) {
            \App\Models\AccountModule::query()->updateOrCreate(
                ['account_id' => $account->id, 'module_key' => 'ai'],
                ['enabled' => true]
            );
        }

        $landing = \App\Models\PlatformSetting::get('cms.landing', []);
        if (! is_array($landing)) {
            $landing = [];
        }
        $enabled = is_array($landing['enabled'] ?? null) ? $landing['enabled'] : [];
        $enabled['integrations'] = false;
        $landing['enabled'] = $enabled;
        \App\Models\PlatformSetting::set('cms.landing', $landing, 'json', 'cms');
        \App\Services\Cms\CmsService::flush();
    }
}
