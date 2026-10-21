<?php

namespace App\Mail;

use App\Models\PaymentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingInvoiceCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PaymentOrder $order) {}

    public function build(): self
    {
        return $this->subject('Invoice '.$this->invoiceNumber().' created')
            ->view('emails.billing.invoice-created')
            ->text('emails.billing.text.invoice-created')
            ->with($this->data())
            ->attachData(
                app(\App\Services\InvoicePdfService::class)->render($this->order),
                $this->invoiceNumber().'.pdf',
                ['mime' => 'application/pdf']
            );
    }

    private function data(): array
    {
        return array_merge(app(\App\Services\InvoicePdfService::class)->viewData($this->order), [
            'order' => $this->order,
            'invoiceNumber' => $this->invoiceNumber(),
            'billingUrl' => route('app.billing.index', ['tab' => 'invoices']),
        ]);
    }

    private function invoiceNumber(): string
    {
        return $this->order->invoice_number ?: $this->order->provider_order_id;
    }
}
