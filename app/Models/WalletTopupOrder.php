<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopupOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'created_by',
        'provider',
        'provider_order_id',
        'provider_payment_id',
        'amount',
        'currency',
        'status',
        'metadata',
        'paid_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
