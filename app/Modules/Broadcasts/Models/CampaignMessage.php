<?php

namespace App\Modules\Broadcasts\Models;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMessage extends Model
{
    use HasFactory;

    protected $table = 'campaign_messages';

    protected $fillable = [
        'campaign_id',
        'campaign_recipient_id',
        'whatsapp_message_id',
        'wamid',
        'status',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime'];

    /**
     * Get the campaign.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the campaign recipient.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(CampaignRecipient::class, 'campaign_recipient_id');
    }

    /**
     * Get the WhatsApp message.
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
