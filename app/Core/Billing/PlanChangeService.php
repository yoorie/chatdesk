<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\BillingEvent;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Self-service plan changes (B5/B13).
 *
 * - Upgrade (target monthly-equivalent price >= current): pay now, new full
 *   cycle starts today, minus a credit for the unused whole days of the paid
 *   current period. GST is charged on the net (see BillingTaxService::quote).
 * - Downgrade (cheaper target): nothing is charged now; the change is stored on
 *   the subscription and applied when the current paid period ends. The
 *   customer then renews at the lower price.
 *
 * Credit = round(value_paid_for_period x unused_whole_days / period_days), per
 * paid period of the current plan, summed. value_paid = taxable value of the
 * order (price - discount) plus any credit carried into it, i.e. the GST-
 * exclusive value the customer actually paid for. Trials, comped/admin
 * periods, past-due and grace periods give no credit.
 */
class PlanChangeService
{
    public const MODE_NEW = 'new';

    public const MODE_RENEWAL = 'renewal';

    public const MODE_UPGRADE = 'upgrade';

    public const MODE_DOWNGRADE = 'downgrade';

    public const MODE_SCHEDULED_RENEWAL = 'scheduled_renewal';

    public function __construct(protected PlanResolver $planResolver) {}

    public static function priceForCycle(Plan $plan, string $cycle): int
    {
        return (int) (($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly) ?? 0);
    }

    /**
     * Classify a self-service purchase/change of $target and compute the credit.
     *
     * @return array{mode:string, credit_amount:int, unused_days:int, period_days:int, from_plan:?array, effective_at:?CarbonInterface, error:?string}
     */
    public function quote(Account $account, Plan $target, string $cycle, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $subscription = $account->subscription()->with('plan')->first();
        $current = $subscription?->plan ?: $this->planResolver->getAccountPlan($account);

        $result = [
            'mode' => self::MODE_NEW,
            'credit_amount' => 0,
            'unused_days' => 0,
            'period_days' => 0,
            'from_plan' => $current ? ['id' => $current->id, 'key' => $current->key, 'name' => $current->name] : null,
            'effective_at' => null,
            'error' => null,
        ];

        if (! $subscription || ! $current) {
            return $result;
        }

        $isCurrent = (int) $current->id === (int) $target->id;

        if (! $this->isPaidActive($subscription, $now)) {
            $result['mode'] = $isCurrent ? self::MODE_RENEWAL : self::MODE_NEW;

            return $result;
        }

        if ($isCurrent) {
            $result['mode'] = self::MODE_RENEWAL;
            if ($subscription->hasScheduledChange()) {
                $result['error'] = "Your plan is scheduled to change to {$subscription->scheduledPlan?->name} on {$subscription->scheduled_change_at->format('j M')}. Cancel the scheduled change to renew {$current->name}.";
            }

            return $result;
        }

        if ($subscription->hasScheduledChange() && (int) $subscription->scheduled_plan_id === (int) $target->id) {
            $result['mode'] = self::MODE_SCHEDULED_RENEWAL;
            $result['effective_at'] = $subscription->scheduled_change_at->copy();
            if (! $subscription->canRenewEarly()) {
                $result['error'] = "Your change to {$target->name} is already scheduled for {$subscription->scheduled_change_at->format('j M')}. You can pay for it when the renewal window opens.";
            }

            return $result;
        }

        $currentCycle = $this->currentCycle($subscription);
        $currentMonthly = $this->monthlyEquivalent($current, $currentCycle);
        $targetMonthly = $this->monthlyEquivalent($target, $cycle);

        if ($targetMonthly < $currentMonthly) {
            $result['mode'] = self::MODE_DOWNGRADE;
            $result['effective_at'] = $subscription->current_period_end->copy();
            if ($subscription->cancel_at_period_end) {
                $result['error'] = "Your plan is set to cancel on {$subscription->current_period_end->format('j M')}. Resume it before scheduling a plan change.";
            }

            return $result;
        }

        $credit = $this->unusedCredit($subscription, $now);
        $result['mode'] = self::MODE_UPGRADE;
        $result['credit_amount'] = $credit['amount'];
        $result['unused_days'] = $credit['unused_days'];
        $result['period_days'] = $credit['period_days'];

        return $result;
    }

    /**
     * Human label for an upgrade credit. Only whole unused days are credited, so
     * when part of a day is lost the label names both counts: a 31-day period
     * upgraded a few hours in reads "Credit for 30 of 31 days unused on Pro".
     */
    public static function creditLabel(int $unusedDays, int $periodDays, ?string $fromPlan): string
    {
        $from = $fromPlan !== null && trim($fromPlan) !== '' ? $fromPlan : 'your previous plan';
        if ($unusedDays <= 0) {
            return "Credit for unused time on {$from}";
        }
        if ($periodDays > $unusedDays) {
            return sprintf('Credit for %d of %d days unused on %s', $unusedDays, $periodDays, $from);
        }

        return sprintf('Credit for %d unused %s of %s', $unusedDays, $unusedDays === 1 ? 'day' : 'days', $from);
    }

    /**
     * Credit for the unused whole days of the current plan's paid periods.
     * A day only counts when a full 24 hours of it remain.
     *
     * @return array{amount:int, unused_days:int, period_days:int}
     */
    public function unusedCredit(Subscription $subscription, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $nowTs = $now->getTimestamp();
        $amount = 0;
        $unusedDays = 0;
        $periodDays = 0;

        foreach ($this->paidPeriods($subscription) as $period) {
            if ((int) ($period['plan_id'] ?? 0) !== (int) $subscription->plan_id) {
                continue;
            }
            $value = max(0, (int) ($period['amount'] ?? 0));
            $startTs = Carbon::parse($period['start'])->getTimestamp();
            $endTs = Carbon::parse($period['end'])->getTimestamp();
            if ($value === 0 || $endTs <= $startTs || $endTs <= $nowTs) {
                continue;
            }

            $days = max(1, (int) round(($endTs - $startTs) / 86400));
            $unused = $nowTs <= $startTs ? $days : min($days, intdiv($endTs - $nowTs, 86400));

            // round(value * unused / days) in integer maths, halves rounded up.
            $amount += intdiv(2 * $value * $unused + $days, 2 * $days);
            $unusedDays += $unused;
            $periodDays += $days;
        }

        return ['amount' => $amount, 'unused_days' => $unusedDays, 'period_days' => $periodDays];
    }

    /**
     * Paid-period ledger for the current plan. Subscriptions activated before
     * this ledger existed fall back to the order that paid the current period.
     *
     * @return array<int, array{plan_id:int, billing_cycle:string, start:string, end:string, amount:int}>
     */
    public function paidPeriods(Subscription $subscription): array
    {
        if (is_array($subscription->paid_periods)) {
            return $subscription->paid_periods;
        }

        if (! $subscription->current_period_start || ! $subscription->current_period_end) {
            return [];
        }

        $windowDays = max(1, (int) PlatformSetting::get('payment.renewal_reminder_days', 7)) + 1;
        $order = PaymentOrder::query()
            ->where('account_id', $subscription->account_id)
            ->where('plan_id', $subscription->plan_id)
            ->where('status', 'paid')
            ->whereNotNull('subscription_activated_at')
            ->where('subscription_activated_at', '>=', $subscription->current_period_start->copy()->subDays($windowDays))
            ->where('subscription_activated_at', '<=', $subscription->current_period_end)
            ->latest('subscription_activated_at')
            ->first();

        if (! $order) {
            return [];
        }

        return [[
            'plan_id' => (int) $subscription->plan_id,
            'billing_cycle' => (string) ($order->billing_cycle ?: 'monthly'),
            'start' => $subscription->current_period_start->toIso8601String(),
            'end' => $subscription->current_period_end->toIso8601String(),
            'amount' => self::orderPeriodValue($order),
        ]];
    }

    public static function orderPeriodValue(PaymentOrder $order): int
    {
        $taxable = $order->taxable_amount !== null
            ? (int) $order->taxable_amount
            : max(0, (int) $order->base_amount - (int) $order->discount_amount);

        return $taxable + (int) ($order->credit_amount ?? 0);
    }

    public function isPaidActive(?Subscription $subscription, ?CarbonInterface $now = null): bool
    {
        $now = $now ? Carbon::instance($now) : now();

        return $subscription
            && $subscription->status === 'active'
            && $subscription->current_period_end
            && $subscription->current_period_end->gt($now)
            && $subscription->plan
            && ((int) ($subscription->plan->price_monthly ?? 0) > 0 || (int) ($subscription->plan->price_yearly ?? 0) > 0);
    }

    /**
     * When the next paid period would end for a purchase in $mode.
     */
    public function periodFor(Subscription|null $subscription, string $mode, string $cycle, ?CarbonInterface $now = null): array
    {
        $now = $now ? Carbon::instance($now) : now();
        $start = match (true) {
            $mode === self::MODE_SCHEDULED_RENEWAL && $subscription?->scheduled_change_at => $subscription->scheduled_change_at->copy(),
            $mode === self::MODE_RENEWAL && $subscription?->canRenewEarly() => $subscription->current_period_end->copy(),
            default => $now->copy(),
        };
        $end = $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Activate a paid order/purchase. Shared by Razorpay, bank/UPI approval and
     * wallet purchases so every path records the paid-period ledger.
     */
    public function activatePaid(
        Account $account,
        Plan $plan,
        ?User $actor,
        array $metadata,
        string $mode,
        int $periodValue,
        SubscriptionService $subscriptions,
        callable $canRenewSamePlan
    ): string {
        $subscription = $account->subscription()->first();
        if ($subscription && $this->applyDueScheduledChange($subscription)) {
            $subscription = $subscription->fresh();
        }
        $cycle = (string) ($metadata['billing_cycle'] ?? 'monthly');
        $currentPlanId = $subscription?->plan_id;

        // Paying for the already scheduled lower plan in the renewal window:
        // prepay the period after the switch date, keep the current plan now.
        if (
            $mode === self::MODE_SCHEDULED_RENEWAL
            && $subscription
            && (int) $subscription->scheduled_plan_id === (int) $plan->id
            && $subscription->scheduled_change_at?->isFuture()
        ) {
            $start = $subscription->scheduled_change_at->copy();
            $end = $cycle === 'yearly' ? $start->copy()->addYear() : $start->copy()->addMonth();
            $periods = $this->paidPeriods($subscription);
            $periods[] = $this->periodEntry($plan->id, $cycle, $start, $end, $periodValue);
            $subscription->forceFill([
                'current_period_end' => $end,
                'scheduled_billing_cycle' => $cycle,
                'paid_periods' => array_values($periods),
                'last_payment_at' => $metadata['paid_at'] ?? now(),
                'last_payment_failed_at' => null,
                'last_error' => null,
            ])->save();
            BillingEvent::create([
                'account_id' => $account->id,
                'actor_id' => $actor?->id,
                'type' => 'scheduled_plan_prepaid',
                'data' => ['plan_key' => $plan->key, 'period_start' => $start->toIso8601String(), 'period_end' => $end->toIso8601String(), 'metadata' => $metadata],
            ]);

            return 'subscription_renewed';
        }

        if ($subscription && (int) $currentPlanId === (int) $plan->id && $canRenewSamePlan($subscription)) {
            $keep = $subscription->current_period_end && $subscription->current_period_end->isFuture()
                ? $this->paidPeriods($subscription)
                : [];
            $renewed = $subscriptions->renew($account, $plan, $actor, 'manual', $metadata + ['source' => 'same_plan_renewal']);
            $keep[] = $this->periodEntry($plan->id, $cycle, $renewed->current_period_start, $renewed->current_period_end, $periodValue);
            $renewed->forceFill([
                'paid_periods' => array_values($keep),
                'scheduled_plan_id' => null,
                'scheduled_billing_cycle' => null,
                'scheduled_change_at' => null,
            ])->save();

            return 'subscription_renewed';
        }

        $changed = $subscriptions->changePlan($account, $plan, $actor, 'manual', $metadata + ['skip_proration' => true]);
        $paidAt = Carbon::parse($metadata['paid_at'] ?? now());
        $end = $cycle === 'yearly' ? $paidAt->copy()->addYear() : $paidAt->copy()->addMonth();
        $changed->forceFill([
            'status' => 'active',
            'trial_ends_at' => null,
            'current_period_start' => $paidAt,
            'current_period_end' => $end,
            'paid_periods' => [$this->periodEntry($plan->id, $cycle, $paidAt, $end, $periodValue)],
        ])->save();

        return 'subscription_activated';
    }

    public function schedule(Subscription $subscription, Plan $plan, string $cycle, ?User $actor = null): Subscription
    {
        $subscription->forceFill([
            'scheduled_plan_id' => $plan->id,
            'scheduled_billing_cycle' => $cycle,
            'scheduled_change_at' => $subscription->current_period_end,
        ])->save();

        BillingEvent::create([
            'account_id' => $subscription->account_id,
            'actor_id' => $actor?->id,
            'type' => 'plan_change_scheduled',
            'data' => [
                'from_plan_key' => $subscription->plan?->key,
                'to_plan_key' => $plan->key,
                'billing_cycle' => $cycle,
                'effective_at' => $subscription->current_period_end?->toIso8601String(),
            ],
        ]);

        return $subscription->fresh(['plan', 'scheduledPlan']);
    }

    public function cancelScheduled(Subscription $subscription, ?User $actor = null, string $reason = 'customer'): Subscription
    {
        $toPlan = $subscription->scheduledPlan;
        $periods = $this->paidPeriods($subscription);
        $subscription->forceFill([
            'scheduled_plan_id' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
            // Drop any prepaid entry for the plan that is no longer coming.
            'paid_periods' => array_values(array_filter($periods, fn ($p) => (int) ($p['plan_id'] ?? 0) === (int) $subscription->plan_id)),
        ])->save();

        BillingEvent::create([
            'account_id' => $subscription->account_id,
            'actor_id' => $actor?->id,
            'type' => 'plan_change_canceled',
            'data' => ['to_plan_key' => $toPlan?->key, 'reason' => $reason],
        ]);

        return $subscription->fresh(['plan']);
    }

    /**
     * Switch to the scheduled plan once its date has passed. Returns true when
     * the subscription row changed.
     */
    public function applyDueScheduledChange(Subscription $subscription, ?CarbonInterface $now = null): bool
    {
        $now = $now ? Carbon::instance($now) : now();
        if (! $subscription->hasScheduledChange() || $subscription->scheduled_change_at->gt($now)) {
            return false;
        }

        $plan = Plan::find($subscription->scheduled_plan_id);
        // Cancellation wins over a pending downgrade.
        if (! $plan || $subscription->cancel_at_period_end || $subscription->status === 'canceled') {
            $this->cancelScheduled($subscription, null, $plan ? 'subscription_canceled' : 'plan_missing');

            return true;
        }

        $changeAt = $subscription->scheduled_change_at->copy();
        $oldPlanKey = $subscription->plan?->key;
        $prepaid = $subscription->current_period_end && $subscription->current_period_end->gt($changeAt);
        $periods = array_values(array_filter(
            $this->paidPeriods($subscription),
            fn ($p) => (int) ($p['plan_id'] ?? 0) === (int) $plan->id
        ));

        $subscription->forceFill([
            'plan_id' => $plan->id,
            'current_period_start' => $prepaid ? $changeAt : $subscription->current_period_start,
            'paid_periods' => $periods,
            'scheduled_plan_id' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
        ])->save();

        BillingEvent::create([
            'account_id' => $subscription->account_id,
            'actor_id' => null,
            'type' => 'plan_changed',
            'data' => [
                'old_plan_key' => $oldPlanKey,
                'new_plan_key' => $plan->key,
                'provider' => $subscription->provider,
                'source' => 'scheduled_downgrade',
                'prepaid' => $prepaid,
                'proration' => ['applied' => false, 'amount_minor' => 0, 'remaining_ratio' => 0],
            ],
        ]);

        return true;
    }

    protected function currentCycle(Subscription $subscription): string
    {
        $periods = $this->paidPeriods($subscription);
        $last = end($periods);
        if (is_array($last) && in_array($last['billing_cycle'] ?? null, ['monthly', 'yearly'], true)) {
            return $last['billing_cycle'];
        }

        if ($subscription->current_period_start && $subscription->current_period_end) {
            return $subscription->current_period_start->diffInDays($subscription->current_period_end, true) > 45 ? 'yearly' : 'monthly';
        }

        return 'monthly';
    }

    protected function monthlyEquivalent(Plan $plan, string $cycle): float
    {
        if ($cycle === 'yearly' && (int) ($plan->price_yearly ?? 0) > 0) {
            return ((int) $plan->price_yearly) / 12;
        }

        return (float) ((int) ($plan->price_monthly ?? 0));
    }

    protected function periodEntry(int $planId, string $cycle, CarbonInterface $start, CarbonInterface $end, int $value): array
    {
        return [
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'start' => Carbon::instance($start)->toIso8601String(),
            'end' => Carbon::instance($end)->toIso8601String(),
            'amount' => max(0, $value),
        ];
    }
}
