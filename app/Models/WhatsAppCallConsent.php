<?php

namespace App\Models;

use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppCallConsent extends Model
{
    protected $table = 'ai_voice_consents';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'phone_number',
        'contact_name',
        'status',
        'source',
        'consented_at',
        'expires_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnection::class, 'whatsapp_connection_id');
    }
}
