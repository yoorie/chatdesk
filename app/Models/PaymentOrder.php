<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'plan_id',
        'provider',
        'payment_method',
        'provider_order_id',
        'provider_payment_id',
        'provider_subscription_id',
        'invoice_number',
        'amount',
        'currency',
        'billing_cycle',
        'base_amount',
        'discount_code',
        'discount_amount',
        'credit_amount',
        'taxable_amount',
        'tax_amount',
        'tax_rate',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'tax_snapshot',
        'proof_path',
        'proof_original_name',
        'proof_uploaded_at',
        'status',
        'metadata',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'paid_at',
        'subscription_activated_at',
        'failed_at'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'tax_snapshot' => 'array',
            'tax_rate' => 'decimal:2',
            'proof_uploaded_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'paid_at' => 'datetime',
            'subscription_activated_at' => 'datetime',
            'failed_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
