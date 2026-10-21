@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
@endphp
<x-emails.billing.layout title="Invoice created">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">Invoice created</h1>
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Your invoice is ready. To activate your plan, pay the total below by bank transfer or UPI, then upload the payment receipt from Billing. We'll email you as soon as it's approved.</p>
    @include('emails.billing.partials.summary')
    <p style="margin:22px 0 0;">
        <a href="{{ $billingUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">Open Billing</a>
    </p>
</x-emails.billing.layout>
