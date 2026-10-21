<?php

namespace App\Modules\Broadcasts\Models;

use App\Models\Account;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'campaigns';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'whatsapp_template_id',
        'created_by',
        'slug',
        'name',
        'description',
        'status',
        'type',
        'template_params',
        'message_text',
        'media_url',
        'media_type',
        'scheduled_at',
        'started_at',
        'completed_at',
        'recipient_type',
        'recipient_filters',
        'custom_recipients',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'send_delay_seconds',
        'respect_opt_out',
        'purge_after_at',
        'metadata'];

    protected $casts = [
        'template_params' => 'array',
        'recipient_filters' => 'array',
        'custom_recipients' => 'array',
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'purge_after_at' => 'datetime'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($campaign) {
            if (! $campaign->slug) {
                $campaign->slug = static::generateSlug($campaign);
            }
        });

        static::updating(function ($campaign) {
            // Regenerate slug if name changes
            if ($campaign->isDirty('name') && ! $campaign->isDirty('slug')) {
                $campaign->slug = static::generateSlug($campaign);
            }
        });
    }

    /**
     * Generate a unique slug for the campaign.
     */
    public static function generateSlug($campaign): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($campaign->name ?? 'campaign');
        $slug = $baseSlug;
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)
            ->where('account_id', $campaign->account_id ?? 0)
            ->where('id', '!=', $campaign->id ?? 0)
            ->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

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
     * Get the WhatsApp template.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'whatsapp_template_id');
    }

    /**
     * Get the user who created the campaign.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get campaign recipients.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Get campaign messages.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    /**
     * Check if campaign can be started.
     */
    public function canStart(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'paused']) && $this->total_recipients > 0;
    }

    /**
     * Check if campaign is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['sending', 'scheduled']);
    }

    /**
     * Get completion percentage.
     */
    public function getCompletionPercentageAttribute(): float
    {
        $totalRecipients = (int) $this->total_recipients;
        if ($totalRecipients <= 0) {
            return 0;
        }

        $processed = min($totalRecipients, max(0, (int) $this->sent_count + (int) $this->failed_count));

        return min(100, round(($processed / $totalRecipients) * 100, 2));
    }

    /**
     * Get delivery rate.
     */
    public function getDeliveryRateAttribute(): float
    {
        $totalRecipients = max(0, (int) $this->total_recipients);
        $deliveredCount = min($totalRecipients, max(0, (int) $this->delivered_count));

        if ($totalRecipients === 0) {
            return 0;
        }

        return min(100, round(($deliveredCount / max(1, $totalRecipients)) * 100, 2));
    }

    /**
     * Get read rate.
     */
    public function getReadRateAttribute(): float
    {
        $sentCount = max(0, (int) $this->sent_count);
        $deliveredCount = min($sentCount, max(0, (int) $this->delivered_count));
        $readCount = min($deliveredCount, max(0, (int) $this->read_count));

        if ($deliveredCount === 0) {
            return 0;
        }

        return min(100, round(($readCount / max(1, $deliveredCount)) * 100, 2));
    }
}
