<?php

namespace App\Core\Billing\Providers;

use App\Models\PaymentOrder;
use App\Models\PlatformSetting;

/**
 * Base for redirect (hosted page) gateways such as Stripe Checkout and PayPal.
 * Subscriptions stay local (same as Razorpay); the gateway only collects the payment.
 */
abstract class HostedCheckoutProvider extends RazorpayBillingProvider
{
    /** Currencies without minor units (Stripe/PayPal send whole numbers). */
    protected const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF', 'HUF', 'TWD'];

    /** Create the hosted checkout and return ['id' => remote id, 'url' => redirect url]. */
    abstract public function createCheckout(PaymentOrder $order, string $returnUrl, string $cancelUrl, string $description): array;

    /** Confirm with the gateway that the checkout is paid. Returns the payment reference or null. */
    abstract public function confirmCheckout(PaymentOrder $order, array $query): ?string;

    public function isEnabled(): bool
    {
        return $this->toBoolean($this->setting('enabled', false)) && $this->hasCredentials();
    }

    abstract protected function hasCredentials(): bool;

    protected function setting(string $key, mixed $default = null): mixed
    {
        return PlatformSetting::get('payment.'.$this->getName().'_'.$key, $default);
    }

    protected function isZeroDecimal(string $currency): bool
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true);
    }

    public function handleWebhook(array $payload): void
    {
        // Handled by GatewayCheckoutController.
    }
}
