<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'account_id',
        'whatsapp_connection_id',
        'meta_template_id',
        'slug',
        'name',
        'language',
        'category',
        'status',
        'quality_score',
        'body_text',
        'header_type',
        'header_text',
        'header_media_url',
        'footer_text',
        'buttons',
        'components',
        'last_synced_at',
        'last_meta_error',
        'is_archived'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($template) {
            if (! $template->slug) {
                $template->slug = static::generateSlug($template);
            }
        });

        static::updating(function ($template) {
            // Regenerate slug if name or connection changes
            if ($template->isDirty(['name', 'whatsapp_connection_id']) && ! $template->isDirty('slug')) {
                $template->slug = static::generateSlug($template);
            }
        });
    }

    /**
     * Generate a unique slug for the template.
     */
    public static function generateSlug($template): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($template->name ?? 'template').'-'.($template->whatsapp_connection_id ?? '');
        $slug = $baseSlug;
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $template->id ?? 0)->exists()) {
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
        'buttons' => 'array',
        'components' => 'array',
        'last_synced_at' => 'datetime',
        'is_archived' => 'boolean'];

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
     * Get template versions.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateVersion::class);
    }

    /**
     * Get template sends.
     */
    public function sends(): HasMany
    {
        return $this->hasMany(WhatsAppTemplateSend::class, 'whatsapp_template_id');
    }

    /**
     * Get variable count from body text.
     */
    public function getVariableCountAttribute(): int
    {
        if (! $this->body_text) {
            return 0;
        }

        preg_match_all('/\{\{(\d+)\}\}/', $this->body_text, $matches);

        return count($matches[0] ?? []);
    }

    /**
     * Check if template has buttons.
     */
    public function getHasButtonsAttribute(): bool
    {
        return ! empty($this->buttons) && is_array($this->buttons) && count($this->buttons) > 0;
    }
}
