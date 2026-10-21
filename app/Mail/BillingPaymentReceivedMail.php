<?php

namespace App\Mail;

use App\Models\PaymentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingPaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentOrder $order) {}

    public function build(): self
    {
        return $this->subject('Payment received for '.$this->invoiceNumber())
            ->view('emails.billing.payment-received')
            ->text('emails.billing.text.payment-received')
            ->with(array_merge(app(\App\Services\InvoicePdfService::class)->viewData($this->order), [
                'order' => $this->order,
                'invoiceNumber' => $this->invoiceNumber(),
                'billingUrl' => route('app.billing.index', ['tab' => 'invoices']),
            ]))
            ->attachData(
                app(\App\Services\InvoicePdfService::class)->render($this->order),
                $this->invoiceNumber().'.pdf',
                ['mime' => 'application/pdf']
            );
    }

    private function invoiceNumber(): string
    {
        return $this->order->invoice_number ?: $this->order->provider_order_id;
    }
}
