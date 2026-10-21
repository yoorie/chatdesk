<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountSurvey extends Model
{
    protected $fillable = [
        'account_id', 'name', 'type', 'trigger', 'status',
        'responses_count', 'average_score', 'questions',
        'auto_create_contact', 'contact_name_field', 'contact_phone_field',
        'contact_email_field', 'auto_tag_names', 'success_message',
        'automation_enabled', 'automation_bot_flow_id',
    ];

    protected function casts(): array
    {
        return [
            'responses_count' => 'integer',
            'average_score' => 'decimal:2',
            'questions' => 'array',
            'auto_create_contact' => 'boolean',
            'auto_tag_names' => 'array',
            'automation_enabled' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(AccountSurveyResponse::class);
    }
}
