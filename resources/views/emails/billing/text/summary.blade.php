@php
    $currency = $invoice['currency'] ?? $order->currency ?? 'INR';
    $money = fn ($amount) => (strtoupper((string) $currency) === 'INR' ? 'Rs. ' : $currency.' ').number_format(((int) $amount) / 100, 2);
    $planName = $invoice['plan_name'] ?? $order->plan?->name ?? 'Subscription';
    $instructions = $invoice['payment_instructions'] ?? [];
@endphp
Invoice: {{ $invoice['number'] ?? $invoiceNumber }}
Plan: {{ $planName }}
@if(($invoice['base_amount'] ?? 0) > 0)
Plan price: {{ $money($invoice['base_amount']) }}
@endif
@if(($invoice['discount_amount'] ?? 0) > 0)
Discount{{ ! empty($invoice['discount_code']) ? ' ('.$invoice['discount_code'].')' : '' }}: -{{ $money($invoice['discount_amount']) }}
@endif
@if(($invoice['credit_amount'] ?? 0) > 0)
{{ $invoice['credit_label'] ?? 'Credit for unused time' }}: -{{ $money($invoice['credit_amount']) }}
@endif
Subtotal: {{ $money($invoice['taxable_amount'] ?? $order->taxable_amount ?: $order->base_amount) }}
{{ ($invoice['tax_type'] ?? 'NONE') !== 'NONE' ? 'GST' : 'Tax' }}@if(($invoice['tax_rate'] ?? 0) > 0) ({{ rtrim(rtrim(number_format((float) $invoice['tax_rate'], 2), '0'), '.') }}%)@endif: {{ $money($invoice['tax_amount'] ?? $order->tax_amount) }}
Total: {{ $money($invoice['total_amount'] ?? $order->amount) }}
@if($invoice['show_payment_instructions'] ?? false)

Bank transfer details:
Account: {{ $instructions['account_name'] ?? '-' }}
Account No. / IFSC: {{ $instructions['account_number'] ?? '-' }} / {{ $instructions['ifsc'] ?? '-' }}
UPI: {{ $instructions['upi_id'] ?? '-' }}
@endif
