<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSurveyResponse extends Model
{
    protected $fillable = [
        'account_survey_id', 'account_id', 'respondent_name', 'respondent_phone',
        'respondent_email', 'score', 'answers', 'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'answers' => 'array',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(AccountSurvey::class, 'account_survey_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
