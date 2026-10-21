<?php

namespace App\Services;

use App\Mail\BillingInvoiceCreatedMail;
use App\Mail\BillingPaymentFailedMail;
use App\Mail\BillingPaymentReceivedMail;
use App\Mail\BillingRenewalReminderMail;
use App\Mail\BillingSubscriptionPastDueMail;
use App\Models\PaymentOrder;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class BillingEmailService
{
    public function __construct(protected MailDeliveryService $mailDelivery) {}

    public function invoiceCreated(PaymentOrder $order): void
    {
        $this->send($order, new BillingInvoiceCreatedMail($order));
    }

    public function paymentReceived(PaymentOrder $order): void
    {
        $this->send($order, new BillingPaymentReceivedMail($order));
    }

    public function paymentFailed(PaymentOrder $order, string $reason): void
    {
        $this->send($order, new BillingPaymentFailedMail($order, $reason));
    }

    public function renewalReminder(Subscription $subscription): void
    {
        $owner = $subscription->account?->owner;
        if (! $owner?->email) {
            return;
        }

        try {
            $this->mailDelivery->sendMailable(
                $owner->email,
                new BillingRenewalReminderMail($subscription),
                account: $subscription->account,
                meta: ['subscription_id' => $subscription->id]
            );
        } catch (\Throwable $exception) {
            Log::warning('Billing renewal reminder email failed', [
                'subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function downgradeScheduled(\App\Models\Account $account, Subscription $subscription): void
    {
        $owner = $account->owner;
        if (! $owner?->email) {
            return;
        }

        try {
            $this->mailDelivery->sendMailable(
                $owner->email,
                new \App\Mail\BillingDowngradeScheduledMail($subscription->loadMissing(['plan', 'scheduledPlan'])),
                account: $account,
                meta: ['subscription_id' => $subscription->id, 'type' => 'downgrade_scheduled']
            );
        } catch (\Throwable $exception) {
            Log::warning('Billing downgrade-scheduled email failed', [
                'subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function subscriptionPastDue(Subscription $subscription): void
    {
        $owner = $subscription->account?->owner;
        if (! $owner?->email) {
            return;
        }

        try {
            $this->mailDelivery->sendMailable(
                $owner->email,
                new BillingSubscriptionPastDueMail($subscription),
                account: $subscription->account,
                meta: ['subscription_id' => $subscription->id]
            );
        } catch (\Throwable $exception) {
            Log::warning('Billing past-due email failed', [
                'subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function send(PaymentOrder $order, \Illuminate\Mail\Mailable $mail): void
    {
        $owner = $order->account?->owner;
        if (! $owner?->email) {
            return;
        }

        try {
            $this->mailDelivery->sendMailable(
                $owner->email,
                $mail,
                account: $order->account,
                meta: ['payment_order_id' => $order->id]
            );
        } catch (\Throwable $exception) {
            Log::warning('Billing email failed', [
                'payment_order_id' => $order->id,
                'mail' => $mail::class,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
