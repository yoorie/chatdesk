Payment received

@php($paidUntil = $order->account?->subscription?->current_period_end)
Thank you, we've received your payment. Your {{ $order->plan?->name ?? brand_name() }} plan is active{{ $paidUntil ? ' until '.$paidUntil->format('d M Y') : '' }}. Your invoice is attached as a PDF.

@include('emails.billing.text.summary')

View Invoice:
{{ $billingUrl }}
