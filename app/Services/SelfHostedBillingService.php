<?php

namespace App\Services;

use App\Core\Billing\DiscountService;
use App\Core\Billing\PlanChangeService;
use App\Core\Billing\PlanResolver;
use App\Core\Billing\SubscriptionService;
use App\Models\Account;
use App\Models\BillingDiscount;
use App\Models\BillingEvent;
use App\Models\PaymentOrder;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SelfHostedBillingService
{
    public function __construct(
        protected DiscountService $discountService,
        protected BillingTaxService $taxService,
        protected SubscriptionService $subscriptionService,
        protected PlanResolver $planResolver,
        protected BillingEmailService $emailService
    ) {}

    public function createOrder(Account $account, Plan $plan, User $actor, array $data = []): PaymentOrder
    {
        if ($plan->requiresAdminApproval() && ! $actor->isSuperAdmin()) {
            throw new \InvalidArgumentException('Enterprise plan activation requires platform admin approval.');
        }

        $billingCycle = in_array($data['billing_cycle'] ?? 'monthly', ['monthly', 'yearly'], true)
            ? ($data['billing_cycle'] ?? 'monthly')
            : 'monthly';
        $paymentMethod = $this->normalizePaymentMethod($data['payment_method'] ?? 'manual');
        $currency = strtoupper((string) app(PlatformSettingsService::class)->get('payment.default_currency', 'INR'));
        $baseAmount = $billingCycle === 'yearly'
            ? (int) ($plan->price_yearly ?? 0)
            : (int) ($plan->price_monthly ?? 0);

        if ($baseAmount <= 0) {
            throw new \InvalidArgumentException('Plan is not billable.');
        }

        $discountPreview = $this->discountService->preview($data['promo_code'] ?? null, $plan, $baseAmount, $currency, $account);
        // Always recalculated here (also before reusing an open order), so the
        // order amount equals the checkout preview at the time of payment.
        $change = $this->planChangeQuote($account, $plan, $billingCycle, $baseAmount, (int) ($discountPreview['amount_off_minor'] ?? 0));
        $taxQuote = $this->taxService->quote(
            $account,
            $baseAmount,
            (int) ($discountPreview['amount_off_minor'] ?? 0),
            $currency,
            $change['credit_amount']
        );

        // Re-opening Razorpay checkout for the same plan/cycle/price reuses the open
        // order instead of creating a new "created" invoice every time the customer
        // closes the popup. Razorpay allows multiple payment attempts per order.
        if ($paymentMethod === 'razorpay') {
            $reusable = PaymentOrder::query()
                ->where('account_id', $account->id)
                ->where('plan_id', $plan->id)
                ->where('created_by', $actor->id)
                ->where('provider', 'razorpay')
                ->where('payment_method', 'razorpay')
                ->whereIn('status', ['created', 'failed'])
                ->whereNull('provider_payment_id')
                ->where('billing_cycle', $billingCycle)
                ->where('currency', $currency)
                ->where('amount', (int) $taxQuote['total_amount'])
                ->where('credit_amount', (int) $taxQuote['credit_amount'])
                ->where('provider_order_id', 'like', 'order_%')
                ->where('created_at', '>=', now()->subHours(12))
                ->when(
                    $discountPreview['code'] ?? null,
                    fn ($query, $code) => $query->where('discount_code', $code),
                    fn ($query) => $query->whereNull('discount_code')
                )
                ->latest('id')
                ->first();

            if ($reusable) {
                $reusableMeta = is_array($reusable->metadata) ? $reusable->metadata : [];
                $reusableMeta['plan_change'] = $this->planChangeMetadata($change);
                $reusable->forceFill(['metadata' => $reusableMeta])->save();

                return $reusable->fresh(['account.owner', 'plan']);
            }

            // The credit shrinks day by day; older open checkouts for this plan
            // carry a stale amount and must not be paid any more.
            PaymentOrder::query()
                ->where('account_id', $account->id)
                ->where('plan_id', $plan->id)
                ->where('provider', 'razorpay')
                ->where('payment_method', 'razorpay')
                ->whereIn('status', ['created', 'failed'])
                ->whereNull('provider_payment_id')
                ->update(['status' => 'expired']);
        }

        $metadata = [
            'billing_cycle' => $billingCycle,
            'discount' => $discountPreview,
            'payment_method_label' => $this->paymentMethodLabel($paymentMethod),
            'payment_instructions' => $this->paymentInstructions($paymentMethod),
            'plan_change' => $this->planChangeMetadata($change),
        ];

        $order = PaymentOrder::create([
            'account_id' => $account->id,
            'plan_id' => $plan->id,
            'provider' => $paymentMethod === 'razorpay' ? 'razorpay' : 'zyptos',
            'payment_method' => $paymentMethod,
            'provider_order_id' => $this->nextOrderReference(),
            'currency' => $currency,
            'billing_cycle' => $billingCycle,
            ...$this->taxService->orderTaxFields($taxQuote),
            // Razorpay checkouts get their invoice number only when paid, so
            // abandoned checkouts do not burn numbers in the GST invoice series.
            ...($paymentMethod === 'razorpay' ? ['invoice_number' => null] : []),
            'discount_code' => $discountPreview['code'] ?? null,
            'status' => 'created',
            'metadata' => $metadata,
            'created_by' => $actor->id,
        ]);

        $this->recordOrderEvent($order, 'invoice_created', 'Invoice created', $actor, [
            'payment_method' => $paymentMethod,
            'amount' => (int) $order->amount,
            'currency' => $currency,
        ]);

        if ($data['send_email'] ?? true) {
            $this->emailService->invoiceCreated($order->fresh(['account.owner', 'plan']));
            $this->recordOrderEvent($order, 'invoice_email_sent', 'Invoice email sent', $actor);
        }

        return $order->fresh(['account.owner', 'plan']);
    }

    public function attachProof(PaymentOrder $order, UploadedFile $file, User $actor): PaymentOrder
    {
        if (! in_array($order->status, ['created', 'pending_approval', 'rejected'], true)) {
            throw new \RuntimeException('Proof can only be uploaded for unpaid orders.');
        }

        if ($order->proof_path) {
            Storage::disk('local')->delete($order->proof_path);
        }

        $path = $file->store("payment-proofs/{$order->account_id}", 'local');
        $meta = is_array($order->metadata) ? $order->metadata : [];
        $meta['proof_uploaded_by'] = $actor->id;

        $order->update([
            'proof_path' => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_uploaded_at' => now(),
            'status' => 'pending_approval',
            'rejected_at' => null,
            'rejection_reason' => null,
            'metadata' => $meta,
        ]);

        $this->recordOrderEvent($order, 'proof_uploaded', 'Payment proof uploaded', $actor, [
            'file_name' => $file->getClientOriginalName(),
        ]);

        return $order->fresh(['account.owner', 'plan']);
    }

    public function approve(PaymentOrder $order, User $actor, ?string $paymentReference = null): PaymentOrder
    {
        return DB::transaction(function () use ($order, $actor, $paymentReference) {
            $order = PaymentOrder::query()->lockForUpdate()->with(['account.owner', 'plan'])->findOrFail($order->id);
            if ($order->status === 'paid') {
                return $order;
            }

            $order->update([
                'status' => 'paid',
                'provider_payment_id' => $paymentReference ?: $order->provider_payment_id ?: 'approved-'.$order->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'paid_at' => now(),
                'failed_at' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->recordOrderEvent($order, 'payment_approved', 'Payment approved', $actor, [
                'payment_reference' => $order->provider_payment_id,
            ]);

            $this->activateSubscription($order, $actor);

            return $order->fresh(['account.owner', 'plan']);
        });
    }

    public function reject(PaymentOrder $order, User $actor, string $reason): PaymentOrder
    {
        $order->update([
            'status' => 'rejected',
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'failed_at' => now(),
        ]);

        $this->emailService->paymentFailed($order->fresh(['account.owner', 'plan']), $reason);
        $this->recordOrderEvent($order, 'payment_rejected', 'Payment rejected', $actor, [
            'reason' => $reason,
        ]);
        app(AppNotificationService::class)->platform(
            'failed_payment',
            'Payment proof rejected',
            "Invoice {$order->invoice_number}: {$reason}",
            'warning',
            route('platform.transactions.index', ['status' => 'rejected']),
            ['payment_order_id' => $order->id, 'account_id' => $order->account_id, 'dedupe_key' => 'payment_order_'.$order->id]
        );
        app(AppNotificationService::class)->workspace(
            $order->account_id,
            'payment_failed',
            'Payment rejected',
            $reason,
            'warning',
            route('app.billing.index', ['tab' => 'invoices']),
            ['payment_order_id' => $order->id, 'dedupe_key' => 'payment_order_'.$order->id]
        );

        return $order->fresh(['account.owner', 'plan']);
    }

    public function sendReminder(PaymentOrder $order, User $actor): PaymentOrder
    {
        if ($order->status === 'paid') {
            throw new \RuntimeException('Paid orders do not need reminders.');
        }

        $this->emailService->invoiceCreated($order->fresh(['account.owner', 'plan']));
        $this->recordOrderEvent($order, 'payment_reminder_sent', 'Payment reminder sent', $actor);
        app(AppNotificationService::class)->workspace(
            $order->account_id,
            'payment_due',
            'Payment reminder sent',
            "Invoice {$order->invoice_number} is pending payment.",
            'warning',
            route('app.billing.index', ['tab' => 'invoices']),
            ['payment_order_id' => $order->id, 'dedupe_key' => 'payment_order_'.$order->id],
            $actor
        );

        return $order->fresh(['account.owner', 'plan']);
    }

    public function activateSubscription(PaymentOrder $order, User $actor): bool
    {
        return DB::transaction(function () use ($order, $actor) {
            $order = PaymentOrder::query()->lockForUpdate()->with(['account.owner', 'plan'])->findOrFail($order->id);
            if ($order->subscription_activated_at) {
                return false;
            }
            if ($order->status !== 'paid') {
                throw new \RuntimeException('Only paid orders can activate subscriptions.');
            }
            $account = $order->account;
            $plan = $order->plan;
            if (! $account || ! $plan) {
                throw new \RuntimeException('Payment order is missing account or plan.');
            }

            $metadata = [
                'payment_id' => $order->provider_payment_id,
                'order_id' => $order->provider_order_id,
                'paid_at' => now(),
                'discount_code' => $order->discount_code,
                'discount_snapshot' => $order->metadata['discount'] ?? null,
                'tax_snapshot' => $order->tax_snapshot,
                'billing_cycle' => $order->billing_cycle ?? ($order->metadata['billing_cycle'] ?? 'monthly'),
                'skip_proration' => true,
                'credit_amount' => (int) ($order->credit_amount ?? 0),
            ];

            $event = app(PlanChangeService::class)->activatePaid(
                $account,
                $plan,
                $actor,
                $metadata,
                (string) ($order->metadata['plan_change']['mode'] ?? ''),
                PlanChangeService::orderPeriodValue($order),
                $this->subscriptionService,
                fn ($subscription) => $this->canRenewSamePlan($subscription)
            );
            $this->recordOrderEvent(
                $order,
                $event,
                $event === 'subscription_renewed' ? 'Subscription renewed' : 'Subscription activated',
                $actor
            );
            $order->update(array_filter([
                'subscription_activated_at' => now(),
                'invoice_number' => $order->invoice_number ? null : $this->taxService->nextInvoiceNumber(),
            ]));

            // Count promo redemptions once per paid order, for every payment path
            // (Razorpay, bank approval), so max_redemptions is enforced.
            if ($order->discount_code) {
                BillingDiscount::where('code', $order->discount_code)->first()?->increment('redemptions');
            }
            DB::afterCommit(fn () => $this->emailService->paymentReceived($order->fresh(['account.owner', 'plan'])));

            return true;
        });
    }

    public function recordOrderEvent(PaymentOrder $order, string $event, string $label, ?User $actor = null, array $meta = []): void
    {
        $order->refresh();
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $timeline = is_array($metadata['timeline'] ?? null) ? $metadata['timeline'] : [];

        $entry = [
            'event' => $event,
            'label' => $label,
            'at' => now()->toIso8601String(),
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'meta' => $meta,
        ];

        $timeline[] = $entry;
        $metadata['timeline'] = array_values($timeline);
        $order->forceFill(['metadata' => $metadata])->save();

        BillingEvent::create([
            'account_id' => $order->account_id,
            'actor_id' => $actor?->id,
            'type' => $event,
            'data' => [
                'payment_order_id' => $order->id,
                'invoice_number' => $order->invoice_number,
                'provider_order_id' => $order->provider_order_id,
                'amount' => (int) $order->amount,
                'currency' => $order->currency,
                'label' => $label,
                'meta' => $meta,
            ],
        ]);
    }

    public function paymentInstructions(string $paymentMethod): array
    {
        $settings = app(PlatformSettingsService::class);

        return match ($paymentMethod) {
            'bank', 'upi' => [
                'upi_id' => $settings->get('payment.upi_id'),
                'upi_payee_name' => $settings->get('payment.upi_payee_name', $settings->get('payment.legal_name', config('app.name', 'ChatDesk'))),
                'account_name' => $settings->get('payment.bank_account_name', $settings->get('payment.legal_name', config('app.name', 'ChatDesk'))),
                'account_number' => $settings->get('payment.bank_account_number'),
                'ifsc' => $settings->get('payment.bank_ifsc'),
                'bank_name' => $settings->get('payment.bank_name'),
            ],
            default => [
                'note' => $settings->get('payment.manual_payment_note', 'Pay using the agreed offline method and upload proof for approval.'),
            ],
        };
    }

    public function normalizePaymentMethod(string $method): string
    {
        return in_array($method, ['bank', 'razorpay', 'stripe', 'paypal'], true) ? $method : 'bank';
    }

    public function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'upi', 'bank' => 'Bank transfer',
            'razorpay' => 'Paid online',
            default => 'Bank transfer',
        };
    }

    /**
     * Plan-change classification + credit for an order; throws for changes that
     * cannot be paid for now (downgrades are scheduled, not charged).
     */
    public function planChangeQuote(Account $account, Plan $plan, string $billingCycle, int $baseAmount, int $discountAmount): array
    {
        $change = app(PlanChangeService::class)->quote($account, $plan, $billingCycle);

        if ($change['error']) {
            throw new \InvalidArgumentException($change['error']);
        }
        if ($change['mode'] === PlanChangeService::MODE_DOWNGRADE) {
            throw new \InvalidArgumentException("Changing to {$plan->name} is scheduled for the end of your current period, so there is nothing to pay now.");
        }
        if ($change['credit_amount'] > 0 && $change['credit_amount'] >= $baseAmount - $discountAmount) {
            throw new \InvalidArgumentException('Your unused credit covers this plan in full. Please contact support to switch.');
        }

        return $change;
    }

    public function planChangeMetadata(array $change): array
    {
        return [
            'mode' => $change['mode'],
            'credit_amount' => (int) $change['credit_amount'],
            'unused_days' => (int) $change['unused_days'],
            'period_days' => (int) $change['period_days'],
            'from_plan' => $change['from_plan'],
            'effective_at' => $change['effective_at']?->toIso8601String(),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    private function nextOrderReference(): string
    {
        do {
            $reference = 'ZYP-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (PaymentOrder::where('provider_order_id', $reference)->exists());

        return $reference;
    }

    private function canRenewSamePlan(?\App\Models\Subscription $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        return in_array($subscription->status, ['canceled', 'cancelled', 'expired', 'past_due'], true)
            || ($subscription->current_period_end && $subscription->current_period_end->isPast())
            || $subscription->canRenewEarly();
    }
}
