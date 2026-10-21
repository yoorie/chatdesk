<?php

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentRun extends Model
{
    protected $fillable = [
        'account_id',
        'ai_agent_id',
        'whatsapp_conversation_id',
        'inbound_message_id',
        'outbound_message_id',
        'status',
        'reason',
        'suggestion',
        'error_message',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'inbound_message_id');
    }

    public function outboundMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'outbound_message_id');
    }
}
