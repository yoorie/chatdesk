<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppContact extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'account_id',
        'wa_id',
        'business_scoped_user_id',
        'parent_business_scoped_user_id',
        'whatsapp_username',
        'slug',
        'name',
        'email',
        'phone',
        'company',
        'notes',
        'status',
        'source',
        'last_seen_at',
        'last_contacted_at',
        'message_count',
        'metadata',
        'custom_fields'];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_contacted_at' => 'datetime',
        'metadata' => 'array',
        'custom_fields' => 'array',
        'message_count' => 'integer',
        'purge_after_at' => 'datetime'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($contact) {
            if (! $contact->phone && $contact->wa_id && static::looksLikePhoneIdentifier($contact->wa_id)) {
                $contact->phone = $contact->wa_id;
            }
            if (! $contact->status) {
                $contact->status = 'active';
            }
            if (! $contact->slug) {
                $contact->slug = static::generateSlug($contact);
            }
        });

        static::updating(function ($contact) {
            // Regenerate slug if wa_id or name changes
            if (($contact->isDirty('wa_id') || $contact->isDirty('name')) && ! $contact->isDirty('slug')) {
                $contact->slug = static::generateSlug($contact);
            }
        });
    }

    public static function looksLikePhoneIdentifier(?string $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' && strlen($digits) >= 8 && strlen($digits) >= strlen($value) - 2;
    }

    /**
     * Generate a unique slug for the contact.
     */
    public static function generateSlug($contact): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($contact->wa_id ?? $contact->name ?? 'contact');
        if ($baseSlug === '') {
            $baseSlug = 'contact';
        }

        $slug = $baseSlug;
        $originalSlug = $slug;
        $counter = 1;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->where('id', '!=', $contact->id ?? 0)
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
     * Get conversations for this contact.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class, 'whatsapp_contact_id');
    }

    /**
     * Get tags for this contact.
     */
    public function tags(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Contacts\Models\ContactTag::class,
            'contact_tag_contact',
            'contact_id',
            'contact_tag_id'
        )->withTimestamps();
    }

    /**
     * Get segments for this contact.
     */
    public function segments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Contacts\Models\ContactSegment::class,
            'contact_segment_contact',
            'contact_id',
            'contact_segment_id'
        )->withTimestamps();
    }

    /**
     * Get activities for this contact.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(\App\Modules\Contacts\Models\ContactActivity::class, 'contact_id');
    }
}
