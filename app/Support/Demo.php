<?php

namespace App\Support;

class Demo
{
    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    /** Mask secret-looking values (recursively) so demo visitors never see real keys. */
    public static function mask(mixed $data): mixed
    {
        if (! static::enabled()) {
            return $data;
        }

        return static::maskValue($data, '');
    }

    protected static function maskValue(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = static::maskValue($v, is_string($k) ? $k : $key);
            }

            return $value;
        }
        if (is_string($value) && $value !== '' && preg_match('/(secret|password|token|api_?key|private|key_id|access_key|client_id|dsn|signing)/i', $key)) {
            return '••••••••';
        }

        return $value;
    }
}
