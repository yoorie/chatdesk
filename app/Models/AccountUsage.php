<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountUsage extends Model
{
    use HasFactory;

    protected $table = 'account_usage';

    protected $fillable = [
        'account_id',
        'period',
        'messages_sent',
        'template_sends',
        'ai_credits_used',
        'ai_requests',
        'ai_estimated_tokens',
        'ai_estimated_cost_minor',
        'razorpay_payment_links_created',
        'razorpay_payment_links_paid',
        'meta_conversations_free_used',
        'meta_conversations_paid',
        'meta_conversations_marketing',
        'meta_conversations_utility',
        'meta_conversations_authentication',
        'meta_conversations_service',
        'meta_estimated_cost_minor',
        'storage_bytes'];

    protected function casts(): array
    {
        return [
            'messages_sent' => 'integer',
            'template_sends' => 'integer',
            'ai_credits_used' => 'integer',
            'ai_requests' => 'integer',
            'ai_estimated_tokens' => 'integer',
            'ai_estimated_cost_minor' => 'integer',
            'razorpay_payment_links_created' => 'integer',
            'razorpay_payment_links_paid' => 'integer',
            'meta_conversations_free_used' => 'integer',
            'meta_conversations_paid' => 'integer',
            'meta_conversations_marketing' => 'integer',
            'meta_conversations_utility' => 'integer',
            'meta_conversations_authentication' => 'integer',
            'meta_conversations_service' => 'integer',
            'meta_estimated_cost_minor' => 'integer',
            'storage_bytes' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
