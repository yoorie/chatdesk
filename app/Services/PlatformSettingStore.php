<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Static public configuration plus one fresh snapshot for runtime/secret settings per request. */
class PlatformSettingStore
{
    private ?Collection $settings = null;

    private ?Request $request = null;

    private ?array $staticSettings = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $request = app()->bound('request') ? request() : null;
        if ($this->request !== $request) {
            $this->flush();
            $this->request = $request;
        }

        if (in_array($key, PlatformStaticSettings::KEYS, true)) {
            $this->staticSettings ??= app(PlatformStaticSettings::class)->all();

            return array_key_exists($key, $this->staticSettings) ? $this->staticSettings[$key] : $default;
        }

        $this->settings ??= PlatformSetting::query()->whereNotIn('key', PlatformStaticSettings::KEYS)->get()->keyBy('key');
        $setting = $this->settings->get($key);

        return $setting ? $setting->value : $default;
    }

    public function flush(): void
    {
        $this->settings = null;
        $this->staticSettings = null;
    }
}
