<?php

namespace App\Modules\Chatbots\Models;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotEdge extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'bot_flow_id',
        'from_node_id',
        'to_node_id',
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function fromNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'from_node_id');
    }

    public function toNode(): BelongsTo
    {
        return $this->belongsTo(BotNode::class, 'to_node_id');
    }
}
