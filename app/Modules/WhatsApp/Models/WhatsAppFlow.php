<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppFlow extends Model
{
    protected $table = 'whatsapp_flows';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'meta_flow_id',
        'name',
        'status',
        'category',
        'json_version',
        'data_api_version',
        'data_channel_uri',
        'flow_json',
        'validation_errors',
        'meta',
        'last_synced_at',
        'last_meta_error',
    ];

    protected function casts(): array
    {
        return [
            'flow_json' => 'array',
            'validation_errors' => 'array',
            'meta' => 'array',
            'last_synced_at' => 'datetime',
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
