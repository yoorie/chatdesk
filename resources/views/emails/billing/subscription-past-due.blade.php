@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
    $planName = $subscription->plan?->name ?? brand_name();
    $endedOn = $subscription->current_period_end?->format('d M Y') ?? 'recently';
@endphp
<x-emails.billing.layout :title="$isTrial ? 'Trial ended' : 'Plan expired'">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">{{ $isTrial ? 'Your free trial has ended' : 'Your plan has expired' }}</h1>
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">
        @if($isTrial)
            Your {{ $planName }} trial ended on <strong>{{ $endedOn }}</strong>.
        @else
            Your {{ $planName }} plan ended on <strong>{{ $endedOn }}</strong> and we haven't received a renewal payment.
        @endif
        Your data is safe, and you can still sign in and view it, but sending messages and other actions are paused until you choose a plan.
    </p>
    <p style="margin:22px 0 0;">
        <a href="{{ $billingUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">{{ $isTrial ? 'Choose a plan' : 'Renew now' }}</a>
    </p>
</x-emails.billing.layout>
