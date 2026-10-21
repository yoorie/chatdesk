@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
@endphp
<x-emails.billing.layout title="Payment needs attention">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">Payment needs attention</h1>
    @php($isManualProof = in_array($order->payment_method, ['bank', 'upi', 'manual'], true))
    @if($isManualProof)
        <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">We couldn't confirm the payment receipt you uploaded for this invoice. Your plan has not been changed.</p>
    @else
        <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Your payment attempt for this invoice didn't go through, so your plan has not been changed. If money was debited, your bank normally reverses it automatically within 5–7 working days. If you already completed the payment on a second attempt, you can ignore this email.</p>
    @endif
    <p style="margin:0 0 14px;padding:12px;border-radius:8px;background:#fff3f3;color:#9b1c1c;"><strong>Reason:</strong> {{ $reason }}</p>
    @include('emails.billing.partials.summary')
    <p style="margin:22px 0 0;">
        <a href="{{ $billingUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">{{ $isManualProof ? 'Upload a new receipt' : 'Try again' }}</a>
    </p>
</x-emails.billing.layout>
