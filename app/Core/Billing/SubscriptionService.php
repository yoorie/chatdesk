<?php

namespace App\Core\Billing;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\BillingEvent;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Services\WalletService;
use Carbon\Carbon;

class SubscriptionService
{
    public function __construct(
        protected PlanResolver $planResolver,
        protected BillingProviderManager $providerManager,
        protected WalletService $walletService,
        protected UsageService $usageService
    ) {}

    /**
     * Start a trial for a account.
     */
    public function startTrial(Account $account, Plan $plan, ?User $actor = null, ?string $providerKey = null): Subscription
    {
        $this->assertAdminApprovalForEnterprise($plan, $actor);

        if ($plan->trial_days <= 0) {
            throw new \InvalidArgumentException('Plan does not support trials.');
        }

        if (! $this->canStartSelfServiceTrial($account, $actor)) {
            throw new \InvalidArgumentException('A free trial has already been used for this email account.');
        }

        $provider = $providerKey ? $this->providerManager->get($providerKey) : $this->providerManager->getDefault();
        if (! $provider) {
            throw new \InvalidArgumentException("Billing provider '{$providerKey}' not found.");
        }

        $subscription = $provider->createSubscription($account, $plan, $actor ?? $account->owner);

        $this->logEvent($account, 'trial_started', [
            'plan_key' => $plan->key,
            'trial_days' => $plan->trial_days,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'provider' => $provider->getName()], $actor);

        return $subscription;
    }

    /**
     * Whether trial offers should be shown to this workspace: it has never been on
     * a paid plan (no paid order, no paid period, no active paid subscription) and
     * neither it nor its owner's other workspaces ever had a trial.
     */
    public function isTrialEligible(Account $account): bool
    {
        $subscription = $account->subscription;
        if ($subscription) {
            if ($subscription->status === 'trialing' || $subscription->trial_ends_at !== null) {
                return false;
            }
            if (! empty($subscription->paid_periods)) {
                return false;
            }
            $plan = $subscription->plan;
            if ($plan && (int) ($plan->price_monthly ?? 0) > 0
                && in_array($subscription->status, ['active', 'past_due', 'canceled', 'cancelled', 'expired'], true)) {
                return false;
            }
        }

        $hasPaidOrder = \App\Models\PaymentOrder::query()
            ->where('account_id', $account->id)
            ->whereIn('status', ['paid', 'fulfilled', 'completed'])
            ->exists();
        if ($hasPaidOrder) {
            return false;
        }

        // No actor: a platform admin viewing the workspace must not see trials the owner can't start.
        return $this->canStartSelfServiceTrial($account, null);
    }

    public function canStartSelfServiceTrial(Account $account, ?User $actor = null): bool
    {
        if ($actor?->isSuperAdmin()) {
            return true;
        }

        $owner = $account->owner ?: $actor;
        $email = strtolower(trim((string) $owner?->email));
        if ($email === '') {
            return true;
        }

        return ! Account::query()
            ->whereHas('owner', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
            ->where(function ($query) {
                $query
                    ->whereHas('subscription', fn ($subscription) => $subscription
                        ->where(function ($inner) {
                            $inner->where('status', 'trialing')
                                ->orWhereNotNull('trial_ends_at');
                        }))
                    ->orWhereHas('billingEvents', fn ($events) => $events->where('type', 'trial_started'));
            })
            ->exists();
    }

    /**
     * Change account plan.
     */
    public function changePlan(Account $account, Plan $newPlan, ?User $actor = null, ?string $providerKey = null, array $metadata = []): Subscription
    {
        $oldPlan = $this->planResolver->getAccountPlan($account);
        $this->assertAdminApprovalForEnterprise($newPlan, $actor);
        $this->assertPlanChangeAllowed($account, $newPlan, $oldPlan);

        $subscription = $account->subscription;
        // Wallet proration (B13) was removed: self-service upgrades are credited
        // up front in the order amount (PlanChangeService) and downgrades are
        // scheduled for period end. Admin/system plan changes never prorate.
        $proration = ['applied' => false, 'amount_minor' => 0, 'remaining_ratio' => 0];

        if (! $subscription) {
            // Create new subscription
            $provider = $providerKey ? $this->providerManager->get($providerKey) : $this->providerManager->getDefault();
            if (! $provider) {
                throw new \InvalidArgumentException("Billing provider '{$providerKey}' not found.");
            }
            $subscription = $provider->createSubscription($account, $newPlan, $actor ?? $account->owner, $metadata);
        } else {
            // Update existing subscription using specified provider or current provider
            $provider = $providerKey ? $this->providerManager->get($providerKey) : $this->providerManager->getForSubscription($subscription);
            if (! $provider) {
                throw new \InvalidArgumentException("Billing provider '{$providerKey}' not found.");
            }
            $subscription = $provider->updateSubscription($subscription, $newPlan, $actor ?? $account->owner, $metadata);
        }

        $this->logEvent($account, 'plan_changed', [
            'old_plan_key' => $oldPlan?->key,
            'new_plan_key' => $newPlan->key,
            'provider' => $subscription->provider,
            'proration' => $proration,
            'metadata' => $metadata], $actor);

        // A direct plan change replaces any pending change and the paid-period
        // ledger; paid activations re-record their own period afterwards.
        $subscription->forceFill([
            'paid_periods' => [],
            'scheduled_plan_id' => null,
            'scheduled_billing_cycle' => null,
            'scheduled_change_at' => null,
        ])->save();

        return $subscription->fresh();
    }

    /**
     * Restart the current plan after a trial, billing period, or canceled subscription ended.
     */
    public function renew(Account $account, Plan $plan, ?User $actor = null, ?string $providerKey = null, array $metadata = []): Subscription
    {
        $this->assertAdminApprovalForEnterprise($plan, $actor);

        $subscription = $account->subscription;

        if (! $subscription) {
            return $this->changePlan($account, $plan, $actor, $providerKey, $metadata + ['skip_proration' => true]);
        }

        $provider = $providerKey
            ? $this->providerManager->get($providerKey)
            : $this->providerManager->getForSubscription($subscription);
        if (! $provider) {
            throw new \InvalidArgumentException("Billing provider '{$providerKey}' not found.");
        }

        $now = now();
        $billingCycle = $metadata['billing_cycle'] ?? 'monthly';
        // Early renewal of the same plan (from the reminder window) appends a new
        // period to the one already paid for instead of discarding the remaining days.
        $periodStart = ((int) $subscription->plan_id === (int) $plan->id && $subscription->canRenewEarly())
            ? $subscription->current_period_end->copy()
            : $now->copy();
        $periodEnd = $billingCycle === 'yearly' ? $periodStart->copy()->addYear() : $periodStart->copy()->addMonth();
        $isPaidPlan = (int) (($billingCycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly) ?? 0) > 0;

        $subscription->update([
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => $subscription->started_at ?: $now,
            'trial_ends_at' => null,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'provider' => $provider->getName(),
            'provider_ref' => $metadata['payment_id'] ?? $metadata['order_id'] ?? $subscription->provider_ref,
            'last_payment_at' => $metadata['paid_at'] ?? ($isPaidPlan ? $now : $subscription->last_payment_at),
            'last_payment_failed_at' => null,
            'last_error' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        $this->logEvent($account, 'subscription_renewed', [
            'plan_key' => $plan->key,
            'provider' => $provider->getName(),
            'period_start' => $periodStart->toIso8601String(),
            'period_end' => $periodEnd->toIso8601String(),
            'early_renewal' => $periodStart->gt($now),
            'metadata' => $metadata,
        ], $actor);

        return $subscription->fresh();
    }

    /**
     * Cancel subscription at period end.
     */
    public function cancelAtPeriodEnd(Account $account, ?User $actor = null, bool $immediately = false): Subscription
    {
        $subscription = $account->subscription;

        if (! $subscription) {
            throw new \InvalidArgumentException('Account has no subscription.');
        }

        $provider = $this->providerManager->getForSubscription($subscription);
        $subscription = $provider->cancelSubscription($subscription, $actor ?? $account->owner, $immediately);

        $this->logEvent($account, 'subscription_canceled', [
            'cancel_at_period_end' => ! $immediately,
            'immediately' => $immediately,
            'provider' => $provider->getName()], $actor);

        return $subscription->fresh();
    }

    public function pause(Account $account, ?User $actor = null, bool $atPeriodEnd = false): Subscription
    {
        $subscription = $account->subscription;

        if (! $subscription) {
            throw new \InvalidArgumentException('Account has no subscription.');
        }

        throw new \InvalidArgumentException('Subscription pause is disabled while recurring gateway subscriptions are disabled.');
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Account $account, ?User $actor = null): Subscription
    {
        $subscription = $account->subscription;

        if (! $subscription) {
            throw new \InvalidArgumentException('Account has no subscription.');
        }

        $provider = $this->providerManager->getForSubscription($subscription);
        $subscription = $provider->resumeSubscription($subscription, $actor ?? $account->owner);

        $this->logEvent($account, 'subscription_resumed', [
            'provider' => $provider->getName()], $actor);

        return $subscription->fresh();
    }

    /**
     * Mark subscription as past due.
     */
    public function markPastDue(Account $account, ?string $reason = null, ?User $actor = null): Subscription
    {
        $subscription = $account->subscription;

        if (! $subscription) {
            throw new \InvalidArgumentException('Account has no subscription.');
        }

        $subscription->update([
            'status' => 'past_due',
            'last_payment_failed_at' => now(),
            'last_error' => $reason]);

        $this->logEvent($account, 'payment_failed', [
            'reason' => $reason], $actor);

        return $subscription->fresh();
    }

    /**
     * Record a manual payment.
     */
    public function recordManualPayment(Account $account, int $amount, ?User $actor = null): Subscription
    {
        $subscription = $account->subscription;

        if (! $subscription) {
            throw new \InvalidArgumentException('Account has no subscription.');
        }

        // Extend period by 1 month
        $periodEnd = $subscription->current_period_end
            ? Carbon::parse($subscription->current_period_end)->addMonth()
            : now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $periodEnd,
            'last_payment_at' => now(),
            'last_payment_failed_at' => null,
            'last_error' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null]);

        $this->logEvent($account, 'payment_recorded', [
            'amount' => $amount,
            'currency' => 'INR',
            'period_end' => $periodEnd->toIso8601String()], $actor);

        return $subscription->fresh();
    }

    /**
     * Sync provider state and normalize local subscription status based on dates.
     */
    public function syncAndNormalize(Subscription $subscription): Subscription
    {
        $provider = $this->providerManager->getForSubscription($subscription);
        $subscription = $provider->syncSubscription($subscription)->fresh();

        // A scheduled downgrade whose date has passed switches the plan first,
        // so the renewal/grace logic below runs against the lower plan.
        if (app(PlanChangeService::class)->applyDueScheduledChange($subscription)) {
            $subscription = $subscription->fresh();
        }

        // End of period cancellation.
        if (
            $subscription->cancel_at_period_end
            && $subscription->current_period_end
            && $subscription->current_period_end->isPast()
            && $subscription->status !== 'canceled'
        ) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_at_period_end' => false,
            ]);

            return $subscription->fresh();
        }

        // Expired trial without conversion.
        if (
            $subscription->status === 'trialing'
            && $subscription->trial_ends_at
            && $subscription->trial_ends_at->isPast()
        ) {
            $subscription->update([
                'status' => 'past_due',
                'last_payment_failed_at' => now(),
                'last_error' => $subscription->last_error ?: 'Trial ended. Upgrade plan to continue.',
            ]);

            return $subscription->fresh();
        }

        // Expired paid period.
        if (
            $subscription->status === 'active'
            && $subscription->current_period_end
            && $subscription->current_period_end->isPast()
        ) {
            $graceDays = (int) PlatformSetting::get('payment.subscription_grace_days', 3);
            if ($subscription->current_period_end->copy()->addDays($graceDays)->isFuture()) {
                return $subscription->fresh();
            }

            $subscription->update([
                'status' => 'past_due',
                'last_payment_failed_at' => now(),
                'last_error' => $subscription->last_error ?: 'Subscription period ended. Renew to continue.',
            ]);
        }

        return $subscription->fresh();
    }

    /**
     * Log a billing event.
     */
    protected function logEvent(Account $account, string $type, array $data = [], ?User $actor = null): void
    {
        BillingEvent::create([
            'account_id' => $account->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'data' => $data]);
    }

    protected function assertPlanChangeAllowed(Account $account, Plan $newPlan, ?Plan $oldPlan): void
    {
        if (! $oldPlan || (int) $oldPlan->id === (int) $newPlan->id) {
            return;
        }

        $violations = $this->planChangeViolations($account, $newPlan);

        if (! empty($violations)) {
            throw new \InvalidArgumentException(
                'Cannot switch to this plan because your account currently exceeds one or more limits: '.implode('; ', $violations)
            );
        }
    }

    /**
     * Current usage that would exceed the target plan's limits (empty when it fits).
     *
     * @return array<int, string>
     */
    public function planChangeViolations(Account $account, Plan $newPlan): array
    {
        $targetLimits = $newPlan->limits ?? [];
        if (! $targetLimits) {
            return [];
        }

        $usage = $this->usageService->getCurrentUsage($account);
        $violations = [];

        $limitChecks = [
            'messages_monthly' => (int) ($usage->messages_sent ?? 0),
            'template_sends_monthly' => (int) ($usage->template_sends ?? 0),
            'ai_credits_monthly' => (int) ($usage->ai_credits_used ?? 0),
        ];

        foreach ($limitChecks as $limitKey => $currentValue) {
            $limit = $targetLimits[$limitKey] ?? null;
            if ($limit === null || (int) $limit === -1) {
                continue;
            }

            if ($currentValue > (int) $limit) {
                $violations[] = "{$limitKey}: {$currentValue} > {$limit}";
            }
        }

        $agentsLimit = $targetLimits['agents'] ?? null;
        if ($agentsLimit !== null && (int) $agentsLimit !== -1) {
            $activeAgents = AccountUser::where('account_id', $account->id)
                ->whereHas('user', fn ($query) => $query->where('is_platform_admin', false))
                ->whereIn('role', ['admin', 'member'])
                ->count();
            if ($activeAgents > (int) $agentsLimit) {
                $violations[] = "agents: {$activeAgents} > {$agentsLimit}";
            }
        }

        $connectionsLimit = $targetLimits['whatsapp_connections'] ?? null;
        if ($connectionsLimit !== null && (int) $connectionsLimit !== -1) {
            $activeConnections = WhatsAppConnection::where('account_id', $account->id)
                ->where('is_active', true)
                ->count();
            if ($activeConnections > (int) $connectionsLimit) {
                $violations[] = "whatsapp_connections: {$activeConnections} > {$connectionsLimit}";
            }
        }

        return $violations;
    }

    protected function assertAdminApprovalForEnterprise(Plan $plan, ?User $actor = null): void
    {
        if (! $plan->requiresAdminApproval()) {
            return;
        }

        if ($actor?->isSuperAdmin()) {
            return;
        }

        throw new \InvalidArgumentException('Enterprise plan activation requires platform admin approval.');
    }
}
