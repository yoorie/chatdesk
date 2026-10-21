<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * ChatDesk open-source edition: internal unlimited mode.
 *
 * There is no billing, no payment gateway and no plan management in this edition.
 * The organisation still gets one internal "plan" row and one always-active
 * subscription row, because module availability and usage counters are keyed on
 * them. Nothing ever expires, is charged, or is shown to users. Plan limits are
 * ignored by EntitlementService.
 *
 * Multi-tenant SaaS with real plans and billing: ChatDesk paid plans
 * (https://chatdesk.yoorie.com) or Zyptos (https://zyptos.com).
 */
class SubscriptionService
{
    /** Kept for call-site compatibility; behaves like changePlan(). */
    public function startTrial(Account $account, Plan $plan, ?User $actor = null, ?string $providerKey = null): Subscription
    {
        return $this->changePlan($account, $plan, $actor);
    }

    public function canStartSelfServiceTrial(Account $account, ?User $actor = null): bool
    {
        return false;
    }

    /** Attach the internal plan to the organisation with a subscription that never ends. */
    public function changePlan(Account $account, Plan $newPlan, ?User $actor = null, ?string $providerKey = null, array $metadata = []): Subscription
    {
        $subscription = Subscription::firstOrNew(['account_id' => $account->id]);
        $subscription->fill([
            'plan_id' => $newPlan->id,
            'status' => 'active',
            'started_at' => $subscription->started_at ?? now(),
            'current_period_start' => $subscription->current_period_start ?? now(),
            'current_period_end' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'provider' => 'internal',
            'last_error' => null,
        ])->save();

        return $subscription->fresh('plan');
    }

    public function syncAndNormalize(Subscription $subscription): Subscription
    {
        return $subscription;
    }
}
