<?php

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeal extends Model
{
    protected $fillable = [
        'account_id',
        'whatsapp_contact_id',
        'whatsapp_conversation_id',
        'owner_id',
        'title',
        'stage',
        'value',
        'currency',
        'source',
        'next_follow_up_at',
        'won_at',
        'lost_at',
        'lost_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'next_follow_up_at' => 'datetime',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }
}
