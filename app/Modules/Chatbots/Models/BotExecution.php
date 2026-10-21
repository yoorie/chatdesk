<?php

namespace App\Modules\Chatbots\Models;

use App\Models\Account;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BotExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'bot_id',
        'bot_flow_id',
        'whatsapp_conversation_id',
        'trigger_event_id',
        'status',
        'started_at',
        'finished_at',
        'error_message',
        'logs'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'logs' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(BotFlow::class, 'bot_flow_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function actionJobs(): HasMany
    {
        return $this->hasMany(BotActionJob::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }
}
