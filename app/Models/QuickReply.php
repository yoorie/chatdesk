<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuickReply extends Model
{
    protected $fillable = [
        'account_id',
        'type',
        'label',
        'shortcut',
        'message',
        'is_active',
        'usage_count',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'usage_count' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public static function shortcutFromLabel(string $label): string
    {
        $shortcut = Str::of($label)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s_-]/', '')
            ->replaceMatches('/[\s-]+/', '_')
            ->trim('_')
            ->toString();

        return $shortcut !== '' ? $shortcut : 'reply';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
