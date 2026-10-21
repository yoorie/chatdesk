<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationOutbox extends Model
{
    protected $table = 'notification_outbox';

    protected $fillable = [
        'account_id',
        'notifiable_type',
        'notifiable_id',
        'channel',
        'notification_class',
        'template_key',
        'recipient',
        'subject',
        'status',
        'attempts',
        'provider_code',
        'provider_message_id',
        'provider_response',
        'failure_reason',
        'queued_at',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'meta' => 'array',
            'queued_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
