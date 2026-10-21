<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccountApiKey extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'token_prefix',
        'token_hash',
        'scopes',
        'last_used_at',
        'last_used_ip',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function requestLogs()
    {
        return $this->hasMany(AccountApiRequestLog::class);
    }

    public static function issue(Account $account, string $name, array $scopes): array
    {
        $token = 'wfy_live_'.Str::random(48);

        $key = static::create([
            'account_id' => $account->id,
            'name' => $name,
            'token_prefix' => substr($token, 0, 18),
            'token_hash' => hash('sha256', $token),
            'scopes' => array_values($scopes),
        ]);

        return [$key, $token];
    }

    public function canUseScope(string $scope): bool
    {
        $scopes = $this->scopes ?: [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
