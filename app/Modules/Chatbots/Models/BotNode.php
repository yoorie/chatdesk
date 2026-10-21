<?php

namespace App\Modules\Chatbots\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'bot_flow_id',
        'type',
        'config',
        'sort_order',
        'pos_x',
        'pos_y'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'sort_order' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function actionJobs(): HasMany
    {
        return $this->hasMany(BotActionJob::class, 'node_id');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(BotEdge::class, 'from_node_id')->orderBy('sort_order');
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(BotEdge::class, 'to_node_id');
    }
}
