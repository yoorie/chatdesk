<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAppointment extends Model
{
    protected $fillable = [
        'account_id', 'external_source', 'external_id', 'title', 'contact_name',
        'contact_phone', 'scheduled_at', 'duration_minutes', 'staff_name',
        'status', 'type', 'location', 'meeting_url', 'description',
        'reminder_enabled', 'reminder_minutes_before', 'reminder_sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'reminder_enabled' => 'boolean',
            'reminder_minutes_before' => 'integer',
            'reminder_sent_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
