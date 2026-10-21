<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_type',
        'percent_off',
        'amount_off',
        'currency',
        'duration',
        'duration_cycles',
        'is_active',
        'max_redemptions',
        'redemptions',
        'starts_at',
        'ends_at',
        'plan_keys',
        'provider',
        'provider_offer_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'percent_off' => 'integer',
            'amount_off' => 'integer',
            'duration_cycles' => 'integer',
            'is_active' => 'boolean',
            'max_redemptions' => 'integer',
            'redemptions' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'plan_keys' => 'array',
            'metadata' => 'array',
        ];
    }

    public function isRedeemableFor(Plan $plan, string $currency): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->max_redemptions !== null && $this->redemptions >= $this->max_redemptions) {
            return false;
        }

        if ($this->discount_type === 'fixed' && strtoupper($this->currency) !== strtoupper($currency)) {
            return false;
        }

        $planKeys = $this->plan_keys ?? [];

        return empty($planKeys) || in_array($plan->key, $planKeys, true);
    }

    public function isNewUserOnly(): bool
    {
        return (bool) (($this->metadata ?? [])['new_user_only'] ?? false);
    }

    public function calculateAmountOff(int $amountMinor): int
    {
        if ($amountMinor <= 0) {
            return 0;
        }

        if ($this->discount_type === 'percent') {
            return min($amountMinor, (int) floor($amountMinor * (($this->percent_off ?? 0) / 100)));
        }

        return min($amountMinor, (int) ($this->amount_off ?? 0));
    }

    public function snapshot(int $amountMinor): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'discount_type' => $this->discount_type,
            'percent_off' => $this->percent_off,
            'amount_off' => $this->amount_off,
            'duration' => $this->duration,
            'duration_cycles' => $this->duration_cycles,
            'new_user_only' => $this->isNewUserOnly(),
            'amount_discounted' => $this->calculateAmountOff($amountMinor),
        ];
    }
}
