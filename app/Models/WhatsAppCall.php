<?php

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCall extends Model
{
    protected $table = 'ai_voice_calls';

    protected $fillable = [
        'account_id',
        'ai_agent_id',
        'whatsapp_connection_id',
        'direction',
        'phone_number',
        'contact_name',
        'provider',
        'provider_call_id',
        'status',
        'route_mode',
        'routed_to',
        'consent_status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'transcript',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
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

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnection::class, 'whatsapp_connection_id');
    }
}
