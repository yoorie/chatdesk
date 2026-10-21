<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DestructiveAuditLog extends Model
{
    protected $fillable = [
        'account_id',
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
