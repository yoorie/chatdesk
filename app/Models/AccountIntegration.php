<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class AccountIntegration extends Model
{
    protected $fillable = [
        'account_id',
        'provider',
        'status',
        'config',
        'features',
        'last_sync_at',
        'events_24h',
        'health',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'features' => 'array',
            'health' => 'array',
            'last_sync_at' => 'datetime',
            'events_24h' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(AccountIntegrationSyncLog::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function secret(string $key): ?string
    {
        $value = $this->configValue($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (! str_starts_with($value, 'enc:')) {
            return trim($value);
        }

        try {
            return Crypt::decryptString(substr($value, 4));
        } catch (Throwable) {
            return null;
        }
    }

    public static function encryptedSecret(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'enc:')) {
            return $value;
        }

        return 'enc:'.Crypt::encryptString($value);
    }
}
