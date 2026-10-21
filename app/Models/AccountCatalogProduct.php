<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountCatalogProduct extends Model
{
    protected $fillable = [
        'account_id', 'name', 'sku', 'category', 'price', 'currency', 'stock',
        'image_url', 'description', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
