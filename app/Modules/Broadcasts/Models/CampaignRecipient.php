<?php

namespace App\Modules\Broadcasts\Models;

use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CampaignRecipient extends Model
{
    use HasFactory;

    protected $table = 'campaign_recipients';

    protected $fillable = [
        'campaign_id',
        'whatsapp_contact_id',
        'phone_number',
        'name',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'failure_reason',
        'retry_after',
        'attempt_count',
        'message_id',
        'wamid',
        'template_params'];

    protected $casts = [
        'template_params' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
        'retry_after' => 'datetime',
        'attempt_count' => 'integer'];

    /**
     * Get the campaign.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the WhatsApp contact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    /**
     * Get the campaign message.
     */
    public function message(): HasOne
    {
        return $this->hasOne(CampaignMessage::class);
    }

    /**
     * Check if recipient message was sent.
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read']);
    }

    /**
     * Check if recipient message was delivered.
     */
    public function isDelivered(): bool
    {
        return in_array($this->status, ['delivered', 'read']);
    }

    /**
     * Check if recipient message was read.
     */
    public function isRead(): bool
    {
        return $this->status === 'read';
    }

    /**
     * Check if recipient message failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Pending recipients whose ecosystem hold (if any) has elapsed.
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'pending')->where(function ($inner) {
            $inner->whereNull('retry_after')->orWhere('retry_after', '<=', now());
        });
    }
}
