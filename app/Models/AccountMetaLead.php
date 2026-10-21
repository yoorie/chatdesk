<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountMetaLead extends Model
{
    protected $fillable = [
        'account_id', 'external_id', 'name', 'phone', 'email', 'city', 'stage',
        'platform', 'form_name', 'ad_name', 'campaign_name', 'source_type',
        'cost_per_lead', 'score', 'assignee_name', 'auto_tags', 'captured_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_lead' => 'integer',
            'score' => 'integer',
            'auto_tags' => 'array',
            'captured_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
