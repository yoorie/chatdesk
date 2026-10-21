<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    /** Built-in pages served at their own URL instead of /page/{slug}. */
    public const SYSTEM_PATHS = [
        'privacy' => '/privacy',
        'terms' => '/terms',
        'refund-policy' => '/refund-policy',
        'cookies' => '/cookies',
        'about' => '/about',
        'contact' => '/contact',
    ];

    protected $fillable = [
        'slug', 'title', 'body', 'meta_title', 'meta_description', 'og_image',
        'status', 'show_in_header', 'show_in_footer', 'menu_order', 'is_system',
    ];

    protected $casts = [
        'show_in_header' => 'boolean',
        'show_in_footer' => 'boolean',
        'is_system' => 'boolean',
        'menu_order' => 'integer',
    ];

    protected static function booted(): void
    {
        $flush = fn () => \App\Services\Cms\CmsService::flush();
        static::saved($flush);
        static::deleted($flush);
    }

    public function scopePublished($q)
    {
        return $q->where('status', 'published');
    }

    public function url(): string
    {
        return self::SYSTEM_PATHS[$this->slug] ?? '/page/'.$this->slug;
    }
}
