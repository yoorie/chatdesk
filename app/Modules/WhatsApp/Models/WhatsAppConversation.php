<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'whatsapp_contact_id',
        'assigned_to',
        'status',
        'priority',
        'last_message_at',
        'last_message_preview',
        'metadata'];

    protected $casts = [
        'last_message_at' => 'datetime',
        'metadata' => 'array'];

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the WhatsApp connection.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConnection::class, 'whatsapp_connection_id');
    }

    /**
     * Get the contact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    /**
     * Get messages for this conversation.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id')
            ->orderBy('created_at');
    }

    /**
     * Get internal notes for this conversation.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(WhatsAppConversationNote::class, 'whatsapp_conversation_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get audit events for this conversation.
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(WhatsAppConversationAuditEvent::class, 'whatsapp_conversation_id')
            ->orderBy('created_at', 'desc');
    }
}
