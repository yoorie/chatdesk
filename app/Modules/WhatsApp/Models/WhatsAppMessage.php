<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'account_id',
        'whatsapp_conversation_id',
        'direction',
        'meta_message_id',
        'type',
        'text_body',
        'payload',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'received_at'];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'received_at' => 'datetime'];

    /**
     * Message types that are not counted as messages (e.g. an emoji reaction on another message).
     */
    public const UNCOUNTED_TYPES = ['reaction'];

    protected static function booted(): void
    {
        // Keep the contact's message counter in step with every stored message, whichever
        // path created it (webhook, inbox, bots, AI, campaigns, API). Inbound and outbound both count.
        static::created(function (WhatsAppMessage $message) {
            if (! $message->whatsapp_conversation_id || in_array($message->type, self::UNCOUNTED_TYPES, true)) {
                return;
            }

            try {
                DB::table('whatsapp_contacts')
                    ->whereIn('id', DB::table('whatsapp_conversations')
                        ->select('whatsapp_contact_id')
                        ->where('id', $message->whatsapp_conversation_id))
                    ->increment('message_count');
            } catch (\Throwable $e) {
                Log::warning('Could not update contact message count', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the conversation.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }
}
