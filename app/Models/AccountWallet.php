<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'balance_minor',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'balance_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'account_wallet_id');
    }
}
