<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AiAgent extends Model
{
    use HasFactory, SoftDeletes;

    public const MODES = ['suggest', 'approval', 'autopilot'];

    public const ROLES = ['support', 'sales', 'sales_support', 'operations', 'custom'];

    public const TONES = ['professional', 'friendly', 'concise', 'empathetic', 'bold'];

    protected $fillable = [
        'account_id',
        'created_by',
        'name',
        'slug',
        'avatar',
        'role',
        'language',
        'tone',
        'mode',
        'is_active',
        'instructions',
        'goal',
        'knowledge_sources',
        'allowed_actions',
        'qualification_fields',
        'guardrails',
        'escalation_rules',
        'handoff_rules',
        'fallback_reply',
        'working_hours',
        'max_auto_replies_per_conversation',
        'max_reply_chars',
        'confidence_threshold',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'knowledge_sources' => 'array',
            'allowed_actions' => 'array',
            'qualification_fields' => 'array',
            'guardrails' => 'array',
            'escalation_rules' => 'array',
            'handoff_rules' => 'array',
            'working_hours' => 'array',
            'max_reply_chars' => 'integer',
            'confidence_threshold' => 'float',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiAgent $agent) {
            if (! $agent->slug) {
                $agent->slug = static::uniqueSlug($agent->account_id, $agent->name);
            }
        });

        static::updating(function (AiAgent $agent) {
            if ($agent->isDirty('name') && ! $agent->isDirty('slug')) {
                $agent->slug = static::uniqueSlug($agent->account_id, $agent->name, $agent->id);
            }
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isAutopilot(): bool
    {
        return $this->mode === 'autopilot';
    }

    protected static function uniqueSlug(int|string|null $accountId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'agent';
        $slug = $base;
        $counter = 2;

        while (static::query()
            ->where('account_id', $accountId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->withTrashed()
            ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
