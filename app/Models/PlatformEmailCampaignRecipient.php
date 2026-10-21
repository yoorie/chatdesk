<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformEmailCampaignRecipient extends Model
{
    protected $fillable = [
        'platform_email_campaign_id',
        'account_id',
        'user_id',
        'email',
        'name',
        'status',
        'attempts',
        'sent_at',
        'failed_at',
        'failure_reason',
        'notification_outbox_id',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailCampaign::class, 'platform_email_campaign_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
