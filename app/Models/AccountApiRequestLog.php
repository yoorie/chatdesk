<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountApiRequestLog extends Model
{
    protected $fillable = [
        'account_id',
        'account_api_key_id',
        'method',
        'path',
        'route_name',
        'status',
        'duration_ms',
        'ip',
        'user_agent',
        'request_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(AccountApiKey::class, 'account_api_key_id');
    }
}
