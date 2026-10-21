<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PlatformSetting extends Model
{
    use HasFactory;

    public const ENCRYPTED_PREFIX = 'encrypted:v1:';

    protected static function booted(): void
    {
        $flush = function (self $setting) {
            app(\App\Services\PlatformSettingStore::class)->flush();
            $static = app(\App\Services\PlatformStaticSettings::class);
            $static->invalidate($setting->key);
            if ($setting->getRawOriginal('key') !== $setting->key) {
                $static->invalidate((string) $setting->getRawOriginal('key'));
            }
        };
        static::saved($flush);
        static::deleted($flush);
    }

    public static function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:^|[._])(?:password|secret|secret_key|token|api_key|private_key|credentials|s3_key)$/i', $key);
    }

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description'];

    /**
     * Get setting value with type casting.
     */
    public function getValueAttribute($value)
    {
        if (is_string($value) && str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            $value = Crypt::decryptString(substr($value, strlen(self::ENCRYPTED_PREFIX)));
        }
        $type = $this->attributes['type'] ?? $this->type ?? 'string';

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => is_array($value) ? $value : json_decode((string) $value, true),
            default => $value,
        };
    }

    /**
     * Set setting value with type conversion.
     */
    public function setValueAttribute($value)
    {
        $type = $this->attributes['type'] ?? $this->type ?? 'string';

        $this->attributes['value'] = match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => is_array($value) || is_object($value)
                ? json_encode($value, JSON_THROW_ON_ERROR)
                : (string) $value,
        };
        if (self::isSensitiveKey((string) ($this->attributes['key'] ?? '')) && $this->attributes['value'] !== '') {
            $this->attributes['value'] = self::ENCRYPTED_PREFIX.Crypt::encryptString($this->attributes['value']);
        }
    }

    /**
     * Get setting by key.
     */
    public static function get(string $key, $default = null)
    {
        return app(\App\Services\PlatformSettingStore::class)->get($key, $default);
    }

    /**
     * Set setting by key.
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general', ?string $description = null): void
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->type = $type;
        $setting->group = $group;
        $setting->description = $description;
        $setting->value = $value;
        $setting->save();
    }
}
