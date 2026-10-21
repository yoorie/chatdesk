<?php

namespace App\Mail;

use App\Models\PaymentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingPaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentOrder $order, public string $reason) {}

    public function build(): self
    {
        $subject = $this->order->invoice_number
            ? 'Payment needs attention for '.$this->order->invoice_number
            : ('Your payment for the '.brand_name().' ').($this->order->plan?->name ?? '').' plan did not go through';

        return $this->subject(preg_replace('/\s+/', ' ', $subject))
            ->view('emails.billing.payment-failed')
            ->text('emails.billing.text.payment-failed')
            ->with(array_merge(app(\App\Services\InvoicePdfService::class)->viewData($this->order), [
                'order' => $this->order,
                'reason' => $this->reason,
                'invoiceNumber' => $this->invoiceNumber(),
                'billingUrl' => route('app.billing.index', ['tab' => 'invoices']),
            ]));
    }

    private function invoiceNumber(): string
    {
        return $this->order->invoice_number ?: $this->order->provider_order_id;
    }
}
