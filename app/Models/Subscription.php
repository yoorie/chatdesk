<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'plan_id',
        'slug',
        'status',
        'started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
        'provider',
        'provider_ref',
        'provider_plan_ref',
        'provider_customer_ref',
        'provider_status',
        'provider_payload',
        'discount_code',
        'discount_snapshot',
        'last_payment_at',
        'last_payment_failed_at',
        'last_error',
        'paid_periods',
        'scheduled_plan_id',
        'scheduled_billing_cycle',
        'scheduled_change_at'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($subscription) {
            if (! $subscription->slug) {
                $subscription->slug = static::generateSlug($subscription);
            }
        });
    }

    /**
     * Generate a unique slug for the subscription.
     */
    public static function generateSlug($subscription): string
    {
        $baseSlug = 'sub-'.($subscription->account_id ?? '').'-'.($subscription->plan_id ?? '');
        $slug = \Illuminate\Support\Str::slug($baseSlug);
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'provider_payload' => 'array',
            'discount_snapshot' => 'array',
            'last_payment_at' => 'datetime',
            'last_payment_failed_at' => 'datetime',
            'paid_periods' => 'array',
            'scheduled_change_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    public function hasScheduledChange(): bool
    {
        return $this->scheduled_plan_id !== null && $this->scheduled_change_at !== null;
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isInTrial(): bool
    {
        return $this->isTrialing() && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * An active paid period that ends within the renewal-reminder window can be
     * renewed early. The new period is appended to the current one, so the
     * customer never loses paid days by acting on the reminder email.
     */
    public function canRenewEarly(): bool
    {
        if ($this->status !== 'active' || ! $this->current_period_end || ! $this->current_period_end->isFuture()) {
            return false;
        }

        $days = max(1, (int) PlatformSetting::get('payment.renewal_reminder_days', 7));

        return $this->current_period_end->lte(now()->addDays($days)->endOfDay());
    }
}
