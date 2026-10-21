@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
@endphp
<x-emails.billing.layout title="Payment received">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">Payment received</h1>
    @php($paidUntil = $order->account?->subscription?->current_period_end)
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Thank you, we've received your payment. Your {{ $order->plan?->name ?? brand_name() }} plan is active{{ $paidUntil ? ' until '.$paidUntil->format('d M Y') : '' }}. Your invoice is attached as a PDF.</p>
    @include('emails.billing.partials.summary')
    <p style="margin:22px 0 0;">
        <a href="{{ $billingUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">View Invoice</a>
    </p>
</x-emails.billing.layout>
