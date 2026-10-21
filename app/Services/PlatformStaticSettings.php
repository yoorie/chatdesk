<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Public display configuration, persisted between requests until an admin changes it. */
class PlatformStaticSettings
{
    private const CACHE_KEY = 'platform:static-settings:v1';

    public const KEYS = [
        'general.platform_url', 'general.timezone', 'general.locale', 'general.date_format', 'general.time_format',
        'branding.platform_name', 'branding.logo_path', 'branding.logo_dark_path',
        'branding.sidebar_icon_path', 'branding.sidebar_icon_dark_path', 'branding.favicon_path', 'branding.favicon_dark_path',
        'branding.primary_color', 'branding.secondary_color', 'branding.support_email', 'branding.support_phone',
        'branding.footer_text', 'branding.show_powered_by', 'payment.default_currency', 'payment.currency_symbol_position',
        'compliance.terms_url', 'compliance.privacy_url', 'compliance.cookie_policy_url',
    ];

    public function all(): array
    {
        $load = fn () => PlatformSetting::query()->whereIn('key', self::KEYS)->get()
            ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => $setting->value])->all();
        // Never publish uncommitted configuration to other requests or workers.
        if (DB::transactionLevel() > 0) {
            return $load();
        }
        $cache = Cache::store(config('static-settings.store', 'file'));
        $snapshot = $cache->get(self::CACHE_KEY);
        if ($snapshot !== null) {
            return $snapshot;
        }

        return $cache->lock(self::CACHE_KEY.':lock', 10)->block(5,
            fn () => $cache->rememberForever(self::CACHE_KEY, $load));
    }

    public function invalidate(string $key): void
    {
        if (! in_array($key, self::KEYS, true)) {
            return;
        }
        $forget = function () {
            $cache = Cache::store(config('static-settings.store', 'file'));
            $cache->lock(self::CACHE_KEY.':lock', 10)->block(5, fn () => $cache->forget(self::CACHE_KEY));
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($forget);
        } else {
            $forget();
        }
    }
}
