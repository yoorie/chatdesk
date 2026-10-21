<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountIntegrationSyncLog extends Model
{
    protected $fillable = [
        'account_id',
        'account_integration_id',
        'initiated_by',
        'provider',
        'status',
        'trigger',
        'started_at',
        'finished_at',
        'duration_ms',
        'created_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'summary',
        'error_message',
        'source_ip',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'skipped_count' => 'integer',
            'error_count' => 'integer',
            'summary' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(AccountIntegration::class, 'account_integration_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
