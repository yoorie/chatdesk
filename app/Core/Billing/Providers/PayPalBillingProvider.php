<?php

namespace App\Core\Billing\Providers;

use App\Models\PaymentOrder;
use Illuminate\Support\Facades\Http;

/** PayPal Checkout (Orders v2, capture on return). Keys: Admin > Settings > Payments. */
class PayPalBillingProvider extends HostedCheckoutProvider
{
    public function getName(): string
    {
        return 'paypal';
    }

    protected function hasCredentials(): bool
    {
        return trim((string) $this->setting('client_id', '')) !== '' && trim((string) $this->setting('client_secret', '')) !== '';
    }

    protected function base(): string
    {
        $configured = config('billing.paypal_api_base');
        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }

        return $this->toBoolean($this->setting('sandbox', true)) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    protected function token(): string
    {
        $res = Http::asForm()->withBasicAuth(trim((string) $this->setting('client_id')), trim((string) $this->setting('client_secret')))
            ->timeout(20)->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if (! $res->successful()) {
            throw new \RuntimeException('PayPal authentication failed. Check the client ID and secret.');
        }

        return (string) $res->json('access_token');
    }

    public function createCheckout(PaymentOrder $order, string $returnUrl, string $cancelUrl, string $description): array
    {
        $currency = strtoupper((string) $order->currency);
        $value = $this->isZeroDecimal($currency)
            ? (string) (int) round($order->amount / 100)
            : number_format($order->amount / 100, 2, '.', '');
        $res = Http::withToken($this->token())->timeout(20)->post($this->base().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order->id,
                'custom_id' => (string) $order->id,
                'description' => mb_substr($description, 0, 120),
                'amount' => ['currency_code' => $currency, 'value' => $value],
            ]],
            'application_context' => ['return_url' => $returnUrl, 'cancel_url' => $cancelUrl, 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING'],
        ]);
        if (! $res->successful()) {
            throw new \RuntimeException('PayPal checkout could not be created: '.($res->json('message') ?? 'HTTP '.$res->status()));
        }
        $approve = collect($res->json('links', []))->firstWhere('rel', 'approve');

        return ['id' => (string) $res->json('id'), 'url' => (string) ($approve['href'] ?? '')];
    }

    public function confirmCheckout(PaymentOrder $order, array $query): ?string
    {
        $id = (string) ($order->provider_order_id ?? '');
        if ($id === '' || (isset($query['token']) && $query['token'] !== $id)) {
            return null;
        }
        $token = $this->token();
        $res = Http::withToken($token)->timeout(20)->withBody('{}', 'application/json')->post($this->base().'/v2/checkout/orders/'.urlencode($id).'/capture');
        $data = $res->json() ?? [];
        if (! $res->successful() && ($data['details'][0]['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED') {
            $data = Http::withToken($token)->get($this->base().'/v2/checkout/orders/'.urlencode($id))->json() ?? [];
        }
        if (($data['status'] ?? '') !== 'COMPLETED') {
            return null;
        }
        $unit = $data['purchase_units'][0] ?? [];
        if ((string) ($unit['reference_id'] ?? '') !== (string) $order->id) {
            return null;
        }

        return (string) ($unit['payments']['captures'][0]['id'] ?? $id);
    }
}
