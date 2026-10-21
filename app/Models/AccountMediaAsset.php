<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AccountMediaAsset extends Model
{
    protected $fillable = [
        'account_id', 'name', 'type', 'disk', 'path', 'mime_type', 'size',
        'source', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function url(): ?string
    {
        return $this->path ? Storage::disk($this->disk ?: 'public')->url($this->path) : null;
    }
}
