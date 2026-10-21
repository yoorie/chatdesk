@php
    $moneyCurrency = strtoupper((string) ($invoice['currency'] ?? $order->currency ?? 'INR'));
    $money = fn ($amount) => ($moneyCurrency === 'INR' ? '₹' : $moneyCurrency.' ').number_format(((int) $amount) / 100, 2);
    $primary = trim((string) ($brand['primary_color'] ?? ''));
    if ($primary === '' || strtolower($primary) === '#3b82f6') {
        $primary = '#00A548';
    }
    $primaryText = $brand['primary_text_color'] ?? '#0f1f19';
    $instructions = $invoice['payment_instructions'] ?? [];
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:collapse;border:1px solid #e3ebe6;border-radius:8px;overflow:hidden;">
    <tr>
        <td style="padding:10px 12px;background:#f7faf8;color:#53645c;font-size:13px;">Invoice</td>
        <td style="padding:10px 12px;text-align:right;font-family:monospace;">{{ $invoice['number'] ?? $invoiceNumber }}</td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f7faf8;color:#53645c;font-size:13px;">Plan</td>
        <td style="padding:10px 12px;text-align:right;">{{ $invoice['plan_name'] ?? $order->plan?->name ?? 'Subscription' }}</td>
    </tr>
    @if(($invoice['base_amount'] ?? 0) > 0)
        <tr>
            <td style="padding:10px 12px;background:#f7faf8;color:#53645c;font-size:13px;">Plan price</td>
            <td style="padding:10px 12px;text-align:right;">{{ $money($invoice['base_amount']) }}</td>
        </tr>
    @endif
    @if(($invoice['discount_amount'] ?? 0) > 0)
        <tr>
            <td style="padding:10px 12px;background:#f7faf8;color:#0b8f47;font-size:13px;">Discount {{ $invoice['discount_code'] ? '('.$invoice['discount_code'].')' : '' }}</td>
            <td style="padding:10px 12px;text-align:right;color:#0b8f47;">-{{ $money($invoice['discount_amount']) }}</td>
        </tr>
    @endif
    @if(($invoice['credit_amount'] ?? 0) > 0)
        <tr>
            <td style="padding:10px 12px;background:#f7faf8;color:#0b8f47;font-size:13px;">{{ $invoice['credit_label'] ?? 'Credit for unused time' }}</td>
            <td style="padding:10px 12px;text-align:right;color:#0b8f47;">-{{ $money($invoice['credit_amount']) }}</td>
        </tr>
    @endif
    <tr>
        <td style="padding:10px 12px;background:#f7faf8;color:#53645c;font-size:13px;">Subtotal</td>
        <td style="padding:10px 12px;text-align:right;">{{ $money($invoice['taxable_amount'] ?? $order->taxable_amount ?: $order->base_amount) }}</td>
    </tr>
    <tr>
        <td style="padding:10px 12px;background:#f7faf8;color:#53645c;font-size:13px;">{{ ($invoice['tax_type'] ?? 'NONE') !== 'NONE' ? 'GST' : 'Tax' }}@if(($invoice['tax_rate'] ?? 0) > 0) ({{ rtrim(rtrim(number_format((float) $invoice['tax_rate'], 2), '0'), '.') }}%)@endif</td>
        <td style="padding:10px 12px;text-align:right;">{{ $money($invoice['tax_amount'] ?? $order->tax_amount) }}</td>
    </tr>
    <tr>
        <td style="padding:12px;background:{{ $primary }};color:{{ $primaryText }};font-weight:700;">Total</td>
        <td style="padding:12px;text-align:right;background:{{ $primary }};color:{{ $primaryText }};font-weight:700;">{{ $money($invoice['total_amount'] ?? $order->amount) }}</td>
    </tr>
</table>
@if($invoice['show_payment_instructions'] ?? false)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:14px 0;border-collapse:collapse;border:1px solid #dfe8e2;border-left:4px solid {{ $primary }};border-radius:8px;overflow:hidden;">
        <tr>
            <td colspan="2" style="padding:11px 12px;background:#fbfdfc;color:#17231f;font-weight:700;">Bank transfer details</td>
        </tr>
        <tr>
            <td style="padding:8px 12px;color:#53645c;font-size:13px;">Account</td>
            <td style="padding:8px 12px;text-align:right;">{{ $instructions['account_name'] ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding:8px 12px;color:#53645c;font-size:13px;">Account No. / IFSC</td>
            <td style="padding:8px 12px;text-align:right;">{{ $instructions['account_number'] ?? '-' }} / {{ $instructions['ifsc'] ?? '-' }}</td>
        </tr>
        <tr>
            <td style="padding:8px 12px;color:#53645c;font-size:13px;">UPI</td>
            <td style="padding:8px 12px;text-align:right;">{{ $instructions['upi_id'] ?? '-' }}</td>
        </tr>
    </table>
@endif
