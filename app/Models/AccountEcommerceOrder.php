<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEcommerceOrder extends Model
{
    protected $fillable = [
        'account_id', 'order_number', 'customer_name', 'customer_phone',
        'amount', 'currency', 'status', 'source', 'payment_url',
        'recovery_status', 'recovered_at', 'placed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'recovered_at' => 'datetime',
            'placed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
