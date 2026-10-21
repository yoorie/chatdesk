<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTemplateSend extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_template_sends';

    protected $fillable = [
        'account_id',
        'whatsapp_template_id',
        'whatsapp_message_id',
        'to_wa_id',
        'variables',
        'status',
        'error_message',
        'sent_at'];

    protected $casts = [
        'variables' => 'array',
        'sent_at' => 'datetime'];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /**
     * Get the message.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
