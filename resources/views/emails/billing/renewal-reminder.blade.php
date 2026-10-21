@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
    $isTrial = $subscription->status === 'trialing';
    $planName = $subscription->plan?->name ?? brand_name();
    $dueDate = $subscription->current_period_end?->format('d M Y') ?? 'the renewal date';
    // A scheduled downgrade means the renewal is for the lower plan.
    $renewPlan = ($subscription->scheduled_plan_id && $subscription->scheduledPlan) ? $subscription->scheduledPlan : $subscription->plan;
    $renewName = $renewPlan?->name ?? $planName;
    $price = (int) ($renewPlan?->price_monthly ?? 0);
    $priceLabel = $price > 0 ? '₹'.number_format($price / 100, $price % 100 ? 2 : 0) : null;
@endphp
<x-emails.billing.layout :title="$isTrial ? 'Your trial ends soon' : 'Renewal reminder'">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">{{ $isTrial ? 'Your free trial ends soon' : 'Your plan is due for renewal' }}</h1>
    @if($isTrial)
        <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Your {{ $planName }} trial ends on <strong>{{ $dueDate }}</strong>. Choose a plan before then to keep your workspace running without interruption.</p>
    @else
        <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Your {{ $planName }} plan is paid until <strong>{{ $dueDate }}</strong>. Nothing is charged automatically, so please renew before that date to avoid any interruption.</p>
        <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Renewing early is safe: the new period starts when the current one ends, so you don't lose any days.@if($renewName !== $planName) Your plan changes to {{ $renewName }} on {{ $dueDate }}, so you renew at the {{ $renewName }} price.@endif @if($priceLabel) The {{ $renewName }} plan is {{ $priceLabel }}/month plus applicable taxes.@endif</p>
    @endif
    <p style="margin:22px 0 0;">
        <a href="{{ $billingUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">{{ $isTrial ? 'Choose a plan' : 'Renew now' }}</a>
    </p>
</x-emails.billing.layout>
