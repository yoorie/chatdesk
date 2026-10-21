<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountWebhookDelivery extends Model
{
    protected $fillable = [
        'account_id',
        'account_webhook_endpoint_id',
        'delivery_id',
        'event',
        'url',
        'status',
        'attempts',
        'duration_ms',
        'payload',
        'response_body',
        'error',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(AccountWebhookEndpoint::class, 'account_webhook_endpoint_id');
    }
}
