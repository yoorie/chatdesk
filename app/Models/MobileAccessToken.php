<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileAccessToken extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'name',
        'device_platform',
        'device_id',
        'push_token',
        'push_provider',
        'push_token_updated_at',
        'token_prefix',
        'token_hash',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'push_token_updated_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function issue(User $user, ?Account $account, string $name = 'Mobile app'): array
    {
        $plainTextToken = 'zym_'.Str::random(64);

        $token = static::create([
            'user_id' => $user->id,
            'account_id' => $account?->id,
            'name' => $name,
            'token_prefix' => substr($plainTextToken, 0, 24),
            'token_hash' => hash('sha256', $plainTextToken),
            'expires_at' => now()->addDays(60),
        ]);

        return [$token, $plainTextToken];
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at) {
            return false;
        }

        return ! $this->expires_at || $this->expires_at->isFuture();
    }
}
