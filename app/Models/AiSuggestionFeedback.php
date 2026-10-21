<?php

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestionFeedback extends Model
{
    protected $fillable = [
        'account_id',
        'user_id',
        'whatsapp_conversation_id',
        'suggestion',
        'verdict',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }
}
