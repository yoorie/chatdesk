<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformEmailCampaign extends Model
{
    protected $fillable = [
        'created_by',
        'name',
        'subject',
        'audience',
        'body',
        'cta_label',
        'cta_url',
        'offer_code',
        'status',
        'recipient_count',
        'sent_count',
        'failed_count',
        'queued_at',
        'started_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PlatformEmailCampaignRecipient::class);
    }
}
