<?php

namespace App\Core\Billing\Providers;

use App\Core\Billing\Contracts\BillingProvider;
use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;

/**
 * Manual Billing Provider
 *
 * Handles subscriptions without external payment processing.
 * Useful for enterprise customers, invoicing, or manual payment handling.
 */
class ManualBillingProvider implements BillingProvider
{
    public function getName(): string
    {
        return 'manual';
    }

    public function isEnabled(): bool
    {
        // Manual provider is always enabled
        return true;
    }

    public function createSubscription(Account $account, Plan $plan, User $actor, array $metadata = []): Subscription
    {
        $now = now();
        $isPaidActivation = isset($metadata['paid_at']) || isset($metadata['payment_id']);
        $shouldSuppressTrial = (bool) ($metadata['suppress_trial'] ?? false);
        $isPaidPlan = (int) ($plan->price_monthly ?? 0) > 0;
        $trialEndsAt = (! $isPaidActivation && ! $shouldSuppressTrial && $plan->trial_days > 0)
            ? $now->copy()->addDays($plan->trial_days)
            : null;

        $requiresPayment = $isPaidPlan && ! $isPaidActivation && ! $trialEndsAt;
        $status = $requiresPayment ? 'past_due' : ($trialEndsAt ? 'trialing' : 'active');
        $periodEnd = $trialEndsAt
            ?? ($requiresPayment
                ? $now
                : (($metadata['billing_cycle'] ?? 'monthly') === 'yearly'
                ? $now->copy()->addYear()
                : $now->copy()->addMonth()));

        return Subscription::updateOrCreate(
            ['account_id' => $account->id],
            [
                'plan_id' => $plan->id,
                'status' => $status,
                'started_at' => $now,
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'provider' => $this->getName(),
                'provider_ref' => $metadata['payment_id'] ?? $metadata['order_id'] ?? null,
                'last_payment_at' => $metadata['paid_at'] ?? null,
                'last_payment_failed_at' => $requiresPayment ? $now : null,
                'last_error' => $requiresPayment ? 'Payment is required before activating this workspace plan.' : null]
        );
    }

    public function updateSubscription(Subscription $subscription, Plan $newPlan, User $actor, array $metadata = []): Subscription
    {
        $isPaidActivation = isset($metadata['paid_at']) || isset($metadata['payment_id']);
        $paidAt = $isPaidActivation
            ? ($metadata['paid_at'] ?? now())
            : null;
        $billingCycleCandidate = $metadata['billing_cycle'] ?? 'monthly';
        $billingCycle = in_array($billingCycleCandidate, ['monthly', 'yearly'], true)
            ? $billingCycleCandidate
            : 'monthly';
        $periodStart = $paidAt ? Carbon::parse($paidAt) : $subscription->current_period_start;
        $periodEnd = $paidAt
            ? ($billingCycle === 'yearly' ? $periodStart->copy()->addYear() : $periodStart->copy()->addMonth())
            : $subscription->current_period_end;

        $subscription->update([
            'plan_id' => $newPlan->id,
            'status' => $isPaidActivation || $subscription->status === 'canceled' ? 'active' : $subscription->status,
            'trial_ends_at' => $isPaidActivation ? null : $subscription->trial_ends_at,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'provider_ref' => $metadata['payment_id'] ?? $metadata['order_id'] ?? $subscription->provider_ref,
            'last_payment_at' => $metadata['paid_at'] ?? $subscription->last_payment_at,
            'last_payment_failed_at' => null,
            'last_error' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null]);

        return $subscription->fresh();
    }

    public function cancelSubscription(Subscription $subscription, User $actor, bool $immediately = false): Subscription
    {
        if ($immediately) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_at_period_end' => false]);
        } else {
            $subscription->update([
                'cancel_at_period_end' => true]);
        }

        return $subscription->fresh();
    }

    public function resumeSubscription(Subscription $subscription, User $actor): Subscription
    {
        $subscription->update([
            'status' => 'active',
            'cancel_at_period_end' => false,
            'canceled_at' => null]);

        return $subscription->fresh();
    }

    public function syncSubscription(Subscription $subscription): Subscription
    {
        // Manual provider has no external source to sync. Date-based expiry and
        // cancellation are normalized by SubscriptionService so unpaid periods do
        // not silently renew themselves.
        return $subscription->fresh();
    }

    public function handleWebhook(array $payload): void
    {
        // Manual provider doesn't receive webhooks
    }

    public function getCheckoutUrl(Account $account, Plan $plan, User $actor, array $metadata = []): ?string
    {
        // Manual provider doesn't have checkout URLs
        return null;
    }
}
