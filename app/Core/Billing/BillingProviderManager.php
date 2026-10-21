<?php

namespace App\Core\Billing;

use App\Core\Billing\Contracts\BillingProvider;
use App\Core\Billing\Providers\ManualBillingProvider;
use App\Core\Billing\Providers\RazorpayBillingProvider;
use Illuminate\Support\Collection;

/**
 * Billing Provider Manager
 *
 * Manages all billing providers and provides access to the active provider.
 */
class BillingProviderManager
{
    protected array $providers = [];

    public function __construct()
    {
        // Register default providers
        $this->register('manual', new ManualBillingProvider);
        $this->register('razorpay', new RazorpayBillingProvider);
        $this->register('stripe', new \App\Core\Billing\Providers\StripeBillingProvider);
        $this->register('paypal', new \App\Core\Billing\Providers\PayPalBillingProvider);
    }

    /**
     * Register a billing provider.
     */
    public function register(string $key, BillingProvider $provider): void
    {
        $this->providers[$key] = $provider;
    }

    /**
     * Get a provider by key.
     */
    public function get(string $key): ?BillingProvider
    {
        return $this->providers[$key] ?? null;
    }

    /**
     * Get the default provider (manual).
     */
    public function getDefault(): BillingProvider
    {
        return $this->get('manual');
    }

    /**
     * Get provider for a subscription.
     */
    public function getForSubscription(\App\Models\Subscription $subscription): BillingProvider
    {
        $providerKey = $subscription->provider ?? 'manual';

        return $this->get($providerKey) ?? $this->getDefault();
    }

    /**
     * Get all enabled providers.
     */
    public function getEnabledProviders(): Collection
    {
        return collect($this->providers)
            ->filter(fn (BillingProvider $provider) => $provider->isEnabled());
    }

    /**
     * Get all registered providers.
     */
    public function getAllProviders(): Collection
    {
        return collect($this->providers);
    }
}
