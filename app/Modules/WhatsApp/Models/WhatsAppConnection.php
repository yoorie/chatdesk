<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class WhatsAppConnection extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_connections';

    protected $fillable = [
        'account_id',
        'name',
        'slug',
        'waba_id',
        'meta_business_id',
        'meta_waba_name',
        'meta_account_review_status',
        'meta_business_verification_status',
        'meta_timezone_id',
        'meta_template_namespace',
        'meta_subscribed_apps',
        'phone_number_id',
        'business_phone',
        'meta_verified_name',
        'phone_number_status',
        'quality_rating',
        'code_verification_status',
        'business_category',
        'business_username',
        'business_about',
        'business_address',
        'business_description',
        'business_email',
        'business_websites',
        'business_vertical',
        'profile_picture_url',
        'profile_picture_handle',
        'profile_synced_at',
        'profile_sync_error',
        'setup_method',
        'connection_mode',
        'coexistence_status',
        'coexistence_metadata',
        'coexistence_last_checked_at',
        'coexistence_last_error',
        'access_token_encrypted',
        'api_version',
        'webhook_verify_token',
        'webhook_subscribed',
        'calling_status',
        'calling_enabled',
        'calling_webhook_subscribed',
        'calling_settings',
        'calling_last_checked_at',
        'calling_last_error',
        'webhook_last_received_at',
        'webhook_last_error',
        'is_active',
        'throughput_cap_per_minute',
        'quiet_hours_start',
        'quiet_hours_end',
        'quiet_hours_timezone'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($connection) {
            if (! $connection->slug) {
                $connection->slug = static::generateSlug($connection);
            }
        });

        static::updating(function ($connection) {
            // Regenerate slug if name changes
            if ($connection->isDirty('name') && ! $connection->isDirty('slug')) {
                $connection->slug = static::generateSlug($connection);
            }
        });
    }

    /**
     * Generate a unique slug for the connection.
     */
    public static function generateSlug($connection): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($connection->name ?? 'connection');
        $slug = $baseSlug;
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)
            ->where('id', '!=', $connection->id ?? 0)
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

    protected $casts = [
        'webhook_subscribed' => 'boolean',
        'calling_enabled' => 'boolean',
        'calling_webhook_subscribed' => 'boolean',
        'calling_settings' => 'array',
        'calling_last_checked_at' => 'datetime',
        'coexistence_metadata' => 'array',
        'coexistence_last_checked_at' => 'datetime',
        'is_active' => 'boolean',
        'webhook_last_received_at' => 'datetime',
        'meta_subscribed_apps' => 'array',
        'business_websites' => 'array',
        'profile_synced_at' => 'datetime',
        'throughput_cap_per_minute' => 'integer'];

    /**
     * Get the decrypted access token.
     */
    public function getAccessTokenAttribute(): ?string
    {
        if (! $this->access_token_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the encrypted access token.
     */
    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value) {
            $this->attributes['access_token_encrypted'] = Crypt::encryptString($value);
        } else {
            $this->attributes['access_token_encrypted'] = null;
        }
    }

    /**
     * Get the account.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get conversations for this connection.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    /**
     * Generate a unique webhook verify token.
     */
    public static function generateVerifyToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
