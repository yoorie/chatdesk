@php
    $planName = $subscription->plan?->name ?? brand_name();
    $endedOn = $subscription->current_period_end?->format('d M Y') ?? 'recently';
@endphp
{{ $isTrial ? 'Your free trial has ended' : 'Your plan has expired' }}

@if($isTrial)
Your {{ $planName }} trial ended on {{ $endedOn }}.
@else
Your {{ $planName }} plan ended on {{ $endedOn }} and we haven't received a renewal payment.
@endif
Your data is safe, and you can still sign in and view it, but sending messages and other actions are paused until you choose a plan.

{{ $isTrial ? 'Choose a plan' : 'Renew now' }}:
{{ $billingUrl }}
