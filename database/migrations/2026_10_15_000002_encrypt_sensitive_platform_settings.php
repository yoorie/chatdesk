<?php

use App\Models\PlatformSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        PlatformSetting::query()->orderBy('id')->chunkById(100, function ($settings) {
            foreach ($settings as $setting) {
                if (PlatformSetting::isSensitiveKey($setting->key)
                    && ! str_starts_with((string) $setting->getRawOriginal('value'), PlatformSetting::ENCRYPTED_PREFIX)) {
                    $setting->value = $setting->value;
                    $setting->save();
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('platform_settings')->orderBy('id')->chunkById(100, function ($settings) {
            foreach ($settings as $setting) {
                if (str_starts_with((string) $setting->value, PlatformSetting::ENCRYPTED_PREFIX)) {
                    DB::table('platform_settings')->where('id', $setting->id)->update([
                        'value' => Crypt::decryptString(substr($setting->value, strlen(PlatformSetting::ENCRYPTED_PREFIX))),
                    ]);
                }
            }
        });
    }
};
