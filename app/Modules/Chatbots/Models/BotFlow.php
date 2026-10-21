<?php

namespace App\Modules\Chatbots\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotFlow extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'bot_id',
        'name',
        'trigger',
        'enabled',
        'priority'];

    protected function casts(): array
    {
        return [
            'trigger' => 'array',
            'enabled' => 'boolean',
            'priority' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(BotNode::class)->orderBy('sort_order');
    }

    public function edges(): HasMany
    {
        return $this->hasMany(BotEdge::class)->orderBy('sort_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(BotExecution::class);
    }
}
