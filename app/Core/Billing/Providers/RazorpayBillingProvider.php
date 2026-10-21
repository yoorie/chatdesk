<?php

namespace App\Core\Billing\Providers;

use App\Core\Billing\Contracts\BillingProvider;
use App\Models\Account;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayBillingProvider implements BillingProvider
{
    protected string $baseUrl = 'https://api.razorpay.com/v1';

    public function __construct()
    {
        $configured = config('billing.razorpay_api_base');
        if (is_string($configured) && trim($configured) !== '') {
            $this->baseUrl = rtrim(trim($configured), '/');
        }
    }

    public function getName(): string
    {
        return 'razorpay';
    }

    public function isEnabled(): bool
    {
        return $this->toBoolean(PlatformSetting::get('payment.razorpay_enabled', false))
            && ! empty($this->getKeyId())
            && ! empty($this->getKeySecret());
    }

    public function createSubscription(Account $account, Plan $plan, User $actor, array $metadata = []): Subscription
    {
        return $this->upsertLocalSubscription($account, $plan, $metadata);
    }

    public function updateSubscription(Subscription $subscription, Plan $newPlan, User $actor, array $metadata = []): Subscription
    {
        $now = now();
        $subscription->update([
            'plan_id' => $newPlan->id,
            'status' => 'active',
            'provider' => $this->getName(),
            'provider_ref' => $metadata['payment_id'] ?? $metadata['order_id'] ?? $subscription->provider_ref,
            'provider_plan_ref' => null,
            'provider_customer_ref' => null,
            'provider_status' => 'paid',
            'provider_payload' => $metadata['provider_payload'] ?? $subscription->provider_payload,
            'discount_code' => $metadata['discount_code'] ?? $subscription->discount_code,
            'discount_snapshot' => $metadata['discount_snapshot'] ?? $subscription->discount_snapshot,
            'trial_ends_at' => null,
            'current_period_start' => $now,
            'current_period_end' => $this->periodEndForCycle($now, $metadata['billing_cycle'] ?? 'monthly'),
            'last_payment_at' => $metadata['paid_at'] ?? $now,
            'last_payment_failed_at' => null,
            'last_error' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
        ]);

        return $subscription->fresh();
    }

    public function cancelSubscription(Subscription $subscription, User $actor, bool $immediately = false): Subscription
    {
        if ($immediately) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
                'cancel_at_period_end' => false,
            ]);
        } else {
            $subscription->update(['cancel_at_period_end' => true]);
        }

        return $subscription->fresh();
    }

    public function resumeSubscription(Subscription $subscription, User $actor): Subscription
    {
        $subscription->update([
            'status' => 'active',
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'last_error' => null,
        ]);

        return $subscription->fresh();
    }

    public function syncSubscription(Subscription $subscription): Subscription
    {
        return $subscription->fresh();
    }

    public function handleWebhook(array $payload): void
    {
        $event = $payload['event'] ?? null;
        if (! $event || str_starts_with((string) $event, 'subscription.')) {
            return;
        }

        if ($event === 'payment.captured' || $event === 'order.paid') {
            $orderId = $payload['payload']['order']['entity']['id'] ?? $payload['payload']['payment']['entity']['order_id'] ?? null;
            $paymentId = $payload['payload']['payment']['entity']['id'] ?? null;

            if (! $orderId) {
                return;
            }

            $paymentOrder = PaymentOrder::where('provider', $this->getName())
                ->where('provider_order_id', $orderId)
                ->first();

            if (! $paymentOrder || $paymentOrder->status === 'paid') {
                return;
            }

            $paymentOrder->update([
                'status' => 'paid',
                'provider_payment_id' => $paymentId ?? $paymentOrder->provider_payment_id,
                'paid_at' => now(),
                'failed_at' => null,
            ]);
        }

        if ($event === 'payment.failed') {
            $orderId = $payload['payload']['payment']['entity']['order_id'] ?? null;
            $paymentId = $payload['payload']['payment']['entity']['id'] ?? null;

            if (! $orderId) {
                return;
            }

            $paymentOrder = PaymentOrder::where('provider', $this->getName())
                ->where('provider_order_id', $orderId)
                ->first();

            if (! $paymentOrder || $paymentOrder->status === 'paid') {
                return;
            }

            $paymentOrder->update([
                'status' => 'failed',
                'provider_payment_id' => $paymentId ?? $paymentOrder->provider_payment_id,
                'failed_at' => now(),
            ]);

            app(\App\Services\AppNotificationService::class)->platform(
                'failed_payment',
                'Razorpay payment failed',
                "Order {$paymentOrder->provider_order_id} failed.",
                'warning',
                route('platform.transactions.index', ['status' => 'failed']),
                ['payment_order_id' => $paymentOrder->id, 'account_id' => $paymentOrder->account_id, 'dedupe_key' => 'payment_order_'.$paymentOrder->id]
            );
            app(\App\Services\AppNotificationService::class)->workspace(
                $paymentOrder->account_id,
                'payment_failed',
                'Payment failed',
                "Invoice {$paymentOrder->invoice_number} could not be paid.",
                'warning',
                route('app.billing.index', ['tab' => 'invoices']),
                ['payment_order_id' => $paymentOrder->id, 'dedupe_key' => 'payment_order_'.$paymentOrder->id]
            );
        }
    }

    public function getCheckoutUrl(Account $account, Plan $plan, User $actor, array $metadata = []): ?string
    {
        return null;
    }

    public function createOrder(Account $account, Plan $plan, User $actor): array
    {
        $amount = (int) ($plan->price_monthly ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException('Plan is not billable.');
        }

        return $this->createCustomOrder(
            amount: $amount,
            receipt: "ws_{$account->id}_plan_{$plan->id}_".time(),
            notes: [
                'account_id' => (string) $account->id,
                'plan_id' => (string) $plan->id,
                'user_id' => (string) $actor->id,
            ]
        );
    }

    public function createCustomOrder(int $amount, string $receipt, array $notes = [], ?string $currency = null): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Razorpay is not enabled.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Order amount must be greater than zero.');
        }

        $currency = strtoupper(trim((string) ($currency ?: PlatformSetting::get('payment.default_currency', 'INR'))));
        if ($currency === '') {
            $currency = 'INR';
        }

        $response = Http::withBasicAuth($this->getKeyId(), $this->getKeySecret())
            ->post("{$this->baseUrl}/orders", [
                'amount' => $amount,
                'currency' => $currency,
                'receipt' => $receipt,
                'notes' => $notes,
            ]);

        $data = $response->json();
        if (! $response->successful()) {
            Log::channel('stack')->error('Razorpay order creation failed', [
                'status' => $response->status(),
                'error' => $data,
            ]);
            throw new \RuntimeException($data['error']['description'] ?? 'Unable to create Razorpay order');
        }

        return $data;
    }

    public function getKeyId(): ?string
    {
        $key = PlatformSetting::get('payment.razorpay_key_id');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    public function getKeySecret(): ?string
    {
        $secret = PlatformSetting::get('payment.razorpay_key_secret');

        return is_string($secret) && trim($secret) !== '' ? trim($secret) : null;
    }

    public function getWebhookSecret(): ?string
    {
        $secret = PlatformSetting::get('payment.razorpay_webhook_secret');

        return is_string($secret) && trim($secret) !== '' ? trim($secret) : null;
    }

    protected function upsertLocalSubscription(Account $account, Plan $plan, array $metadata = []): Subscription
    {
        $now = now();

        return Subscription::updateOrCreate(
            ['account_id' => $account->id],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => $now,
                'trial_ends_at' => null,
                'current_period_start' => $now,
                'current_period_end' => $this->periodEndForCycle($now, $metadata['billing_cycle'] ?? 'monthly'),
                'provider' => $this->getName(),
                'provider_ref' => $metadata['payment_id'] ?? $metadata['order_id'] ?? null,
                'provider_plan_ref' => null,
                'provider_customer_ref' => null,
                'provider_status' => 'paid',
                'provider_payload' => $metadata['provider_payload'] ?? null,
                'discount_code' => $metadata['discount_code'] ?? null,
                'discount_snapshot' => $metadata['discount_snapshot'] ?? null,
                'last_payment_at' => $metadata['paid_at'] ?? $now,
                'last_payment_failed_at' => null,
                'last_error' => null,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
            ]
        )->fresh();
    }

    protected function periodEndForCycle(Carbon $start, string $cycle): Carbon
    {
        return in_array($cycle, ['yearly', 'annual'], true)
            ? $start->copy()->addYear()
            : $start->copy()->addMonth();
    }

    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
