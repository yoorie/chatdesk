<?php

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCallVoiceSession extends Model
{
    protected $table = 'whatsapp_call_voice_sessions';

    protected $fillable = [
        'account_id',
        'whatsapp_call_id',
        'whatsapp_connection_id',
        'ai_agent_id',
        'direction',
        'status',
        'worker_id',
        'local_sdp',
        'remote_sdp',
        'transcript',
        'metadata',
        'claimed_at',
        'connected_at',
        'ended_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'transcript' => 'array',
            'metadata' => 'array',
            'claimed_at' => 'datetime',
            'connected_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCall::class, 'whatsapp_call_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnection::class, 'whatsapp_connection_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}
