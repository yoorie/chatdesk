<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountWebhookEndpoint extends Model
{
    protected $fillable = [
        'account_id',
        'url',
        'secret',
        'events',
        'is_enabled',
        'last_tested_at',
        'last_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function deliveries()
    {
        return $this->hasMany(AccountWebhookDelivery::class);
    }
}
