<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'source',
        'ip_address',
        'user_agent',
        'handled_at',
        'handled_by',
        'converted_account_id',
        'converted_contact_id',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function convertedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'converted_account_id');
    }

    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\WhatsApp\Models\WhatsAppContact::class, 'converted_contact_id');
    }
}
