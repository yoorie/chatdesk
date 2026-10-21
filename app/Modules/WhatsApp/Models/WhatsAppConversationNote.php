<?php

namespace App\Modules\WhatsApp\Models;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppConversationNote extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversation_notes';

    protected $fillable = [
        'account_id',
        'whatsapp_conversation_id',
        'created_by',
        'note'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
