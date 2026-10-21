<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMetaEventLog extends Model
{
    protected $table = 'whatsapp_meta_event_logs';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'field',
        'event_type',
        'object_id',
        'status',
        'message',
        'payload',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
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
