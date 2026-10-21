<?php

namespace App\Services;

use App\Models\PaymentOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function render(PaymentOrder $order): string
    {
        return Pdf::loadView('billing.invoice-pdf', $this->viewData($order->loadMissing(['account.owner', 'plan'])))
            ->setPaper('a4')
            ->output();
    }

    public function viewData(PaymentOrder $order): array
    {
        $order->loadMissing(['account.owner', 'plan']);

        $snapshot = is_array($order->tax_snapshot) ? $order->tax_snapshot : [];
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $discount = is_array($metadata['discount'] ?? null) ? $metadata['discount'] : null;
        $paymentInstructions = is_array($metadata['payment_instructions'] ?? null) ? $metadata['payment_instructions'] : [];
        $isPaymentInstructionVisible = ! in_array($order->status, ['paid', 'cancelled', 'canceled', 'void', 'voided'], true)
            && in_array($order->payment_method, ['bank', 'upi', 'manual'], true);
        // Customer-facing label; older orders stored gateway names here.
        $paymentMethodLabel = match (true) {
            in_array($order->payment_method, ['bank', 'upi', 'manual'], true) => 'Bank transfer',
            $order->payment_method === 'razorpay' || $order->provider === 'razorpay' => 'Paid online',
            default => $metadata['payment_method_label'] ?? 'Bank transfer',
        };
        $branding = app(BrandingService::class)->getAll();
        $primaryColor = $this->normalizeHexColor($branding['primary_color'] ?? null, '#00A548');

        return [
            'order' => $order,
            'brand' => [
                'name' => trim((string) ($branding['platform_name'] ?? '')) ?: (brand_name()),
                'logo_url' => $this->absoluteUrl($branding['logo_url'] ?? null),
                'logo_data_uri' => $this->logoDataUri($branding['logo_url'] ?? null),
                'primary_color' => $primaryColor,
                'primary_text_color' => $this->contrastColor($primaryColor),
            ],
            'invoice' => [
                // Customer-facing: never a gateway id (Razorpay order_/pay_). Before an invoice number
                // is issued, the payment date is the friendliest reference we have.
                'number' => $this->invoiceLabel($order),
                'has_invoice_number' => filled($order->invoice_number),
                'date' => $order->created_at?->format('d M Y'),
                'paid_at' => $order->paid_at?->format('d M Y'),
                'status' => strtoupper((string) $order->status),
                'plan_name' => $order->plan?->name ?? 'Subscription',
                'billing_cycle' => $order->billing_cycle ?? ($metadata['billing_cycle'] ?? null),
                'payment_method' => $paymentMethodLabel,
                'payment_instructions' => $paymentInstructions,
                'show_payment_instructions' => $isPaymentInstructionVisible,
                'supplier' => $snapshot['supplier'] ?? app(BillingTaxService::class)->supplierProfile(),
                'customer' => $snapshot['customer'] ?? app(BillingTaxService::class)->customerProfile($order->account),
                'sac_code' => $snapshot['sac_code'] ?? null,
                'tax_type' => $snapshot['tax_type'] ?? null,
                'currency' => $order->currency,
                'base_amount' => (int) ($order->base_amount ?: ($snapshot['base_amount'] ?? 0)),
                'discount_code' => $order->discount_code ?: ($discount['code'] ?? null),
                'discount_name' => $discount['name'] ?? null,
                'discount_amount' => (int) $order->discount_amount,
                'credit_amount' => (int) ($order->credit_amount ?? 0),
                'credit_label' => $this->creditLabel($order),
                'taxable_amount' => (int) $order->taxable_amount,
                'tax_rate' => (float) $order->tax_rate,
                'tax_amount' => (int) $order->tax_amount,
                'cgst_amount' => (int) $order->cgst_amount,
                'sgst_amount' => (int) $order->sgst_amount,
                'igst_amount' => (int) $order->igst_amount,
                'total_amount' => (int) $order->amount,
            ],
        ];
    }

    /** The invoice number, or "Payment on 17 Oct 2026" when none has been issued yet. */
    public function invoiceLabel(PaymentOrder $order): string
    {
        if (filled($order->invoice_number)) {
            return (string) $order->invoice_number;
        }

        return 'Payment on '.($order->created_at ?? now())->format('j M Y');
    }

    /**
     * "Credit for 7 of 30 days unused on Starter" for upgrade invoices.
     */
    protected function creditLabel(PaymentOrder $order): ?string
    {
        if ((int) ($order->credit_amount ?? 0) <= 0) {
            return null;
        }
        $change = is_array($order->metadata['plan_change'] ?? null) ? $order->metadata['plan_change'] : [];

        return \App\Core\Billing\PlanChangeService::creditLabel(
            (int) ($change['unused_days'] ?? 0),
            (int) ($change['period_days'] ?? 0),
            $change['from_plan']['name'] ?? 'previous plan'
        );
    }

    protected function absoluteUrl(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        return str_starts_with($url, '/') ? url($url) : $url;
    }

    protected function logoDataUri(?string $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        $relative = is_string($path) ? ltrim($path, '/') : '';
        $storagePrefix = 'storage/';

        if (! str_starts_with($relative, $storagePrefix)) {
            return null;
        }

        $diskPath = substr($relative, strlen($storagePrefix));
        if (! Storage::disk('public')->exists($diskPath)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($diskPath);
        $mime = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }

    protected function normalizeHexColor(?string $color, string $fallback): string
    {
        $color = trim((string) $color);

        return preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : $fallback;
    }

    protected function contrastColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $luminance > 145 ? '#0f1f19' : '#ffffff';
    }
}
