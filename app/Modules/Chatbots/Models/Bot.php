<?php

namespace App\Modules\Chatbots\Models;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'description',
        'status',
        'is_default',
        'applies_to',
        'stop_on_first_flow',
        'session_timeout_minutes',
        'session_resume_mode',
        'session_expired_message',
        'version',
        'created_by',
        'updated_by'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'applies_to' => 'array',
            'stop_on_first_flow' => 'boolean',
            'session_timeout_minutes' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function flows(): HasMany
    {
        return $this->hasMany(BotFlow::class)->orderBy('priority');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(BotExecution::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function appliesToConnection(int $connectionId): bool
    {
        // Backward compatibility: older bots may have null applies_to.
        // Treat that as "all connections" so active bots still run.
        if (empty($this->applies_to) || ! is_array($this->applies_to)) {
            return true;
        }

        if ($this->applies_to['all_connections'] ?? false) {
            return true;
        }

        $connectionIds = $this->applies_to['connection_ids'] ?? [];
        if (! is_array($connectionIds)) {
            $connectionIds = [$connectionIds];
        }
        $connectionIds = array_values(array_unique(array_map(
            static fn ($id) => (int) $id,
            array_filter($connectionIds, static fn ($id) => is_numeric($id))
        )));

        return in_array((int) $connectionId, $connectionIds, true);
    }
}
