<?php

namespace App\Core\Billing\Providers;

use App\Models\PaymentOrder;
use Illuminate\Support\Facades\Http;

/** Stripe Checkout (one-time payment per billing period). Keys: Admin > Settings > Payments. */
class StripeBillingProvider extends HostedCheckoutProvider
{
    protected function apiBase(): string
    {
        $configured = config('billing.stripe_api_base', 'https://api.stripe.com/v1');

        return rtrim(is_string($configured) && trim($configured) !== '' ? trim($configured) : 'https://api.stripe.com/v1', '/');
    }

    public function getName(): string
    {
        return 'stripe';
    }

    protected function hasCredentials(): bool
    {
        return trim((string) $this->setting('secret_key', '')) !== '';
    }

    protected function http()
    {
        return Http::asForm()->withToken(trim((string) $this->setting('secret_key')))->timeout(20);
    }

    public function createCheckout(PaymentOrder $order, string $returnUrl, string $cancelUrl, string $description): array
    {
        $currency = strtolower((string) $order->currency);
        $amount = (int) $order->amount;
        if ($this->isZeroDecimal($currency)) {
            $amount = (int) round($amount / 100);
        }
        $res = $this->http()->post($this->apiBase().'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $returnUrl.(str_contains($returnUrl, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order->id,
            'customer_email' => $order->account?->owner?->email,
            'metadata' => ['payment_order_id' => (string) $order->id],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => ['name' => $description],
                ],
            ]],
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('Stripe checkout could not be created: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }

        return ['id' => (string) $res->json('id'), 'url' => (string) $res->json('url')];
    }

    public function confirmCheckout(PaymentOrder $order, array $query): ?string
    {
        $sessionId = (string) ($order->provider_order_id ?? '');
        if ($sessionId === '' || (isset($query['session_id']) && $query['session_id'] !== $sessionId)) {
            return null;
        }
        $res = $this->http()->get($this->apiBase().'/checkout/sessions/'.urlencode($sessionId));
        if (! $res->successful() || $res->json('payment_status') !== 'paid') {
            return null;
        }
        if ((string) $res->json('client_reference_id') !== (string) $order->id) {
            return null;
        }

        return (string) ($res->json('payment_intent') ?: $sessionId);
    }

    /** Verify a Stripe-Signature header (v1 HMAC-SHA256, 5 minute tolerance). */
    public function verifyWebhook(string $payload, string $header): bool
    {
        $secret = trim((string) $this->setting('webhook_secret', ''));
        if ($secret === '' || $header === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $kv) {
            [$k, $v] = array_pad(explode('=', trim($kv), 2), 2, '');
            $parts[$k][] = $v;
        }
        $ts = (int) ($parts['t'][0] ?? 0);
        if ($ts <= 0 || abs(time() - $ts) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $ts.'.'.$payload, $secret);
        foreach ($parts['v1'] ?? [] as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
