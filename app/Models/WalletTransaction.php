<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'account_wallet_id',
        'actor_id',
        'direction',
        'amount_minor',
        'balance_after_minor',
        'currency',
        'source',
        'status',
        'reference',
        'notes',
        'meta',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'balance_after_minor' => 'integer',
        'meta' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AccountWallet::class, 'account_wallet_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
