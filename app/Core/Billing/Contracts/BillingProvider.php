<?php

namespace App\Core\Billing\Contracts;

use App\Models\Account;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

/**
 * Billing Provider Interface
 *
 * All payment gateways must implement this interface to integrate with the billing system.
 */
interface BillingProvider
{
    /**
     * Get the provider name/key.
     */
    public function getName(): string;

    /**
     * Check if this provider is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Create a subscription for a account.
     *
     * @param  User  $actor  The user performing the action
     * @param  array  $metadata  Additional metadata (e.g., payment method, billing address)
     */
    public function createSubscription(Account $account, Plan $plan, User $actor, array $metadata = []): Subscription;

    /**
     * Update subscription (e.g., change plan).
     */
    public function updateSubscription(Subscription $subscription, Plan $newPlan, User $actor, array $metadata = []): Subscription;

    /**
     * Cancel subscription.
     *
     * @param  bool  $immediately  Cancel immediately or at period end
     */
    public function cancelSubscription(Subscription $subscription, User $actor, bool $immediately = false): Subscription;

    /**
     * Resume a canceled subscription.
     */
    public function resumeSubscription(Subscription $subscription, User $actor): Subscription;

    /**
     * Sync subscription status from provider.
     */
    public function syncSubscription(Subscription $subscription): Subscription;

    /**
     * Handle webhook from provider.
     */
    public function handleWebhook(array $payload): void;

    /**
     * Get checkout URL for a plan.
     *
     * @return string|null Checkout URL or null if not supported
     */
    public function getCheckoutUrl(Account $account, Plan $plan, User $actor, array $metadata = []): ?string;
}
