@php
    $isTrial = $subscription->status === 'trialing';
    $planName = $subscription->plan?->name ?? brand_name();
    $dueDate = $subscription->current_period_end?->format('d M Y') ?? 'the renewal date';
    // A scheduled downgrade means the renewal is for the lower plan.
    $renewPlan = ($subscription->scheduled_plan_id && $subscription->scheduledPlan) ? $subscription->scheduledPlan : $subscription->plan;
    $renewName = $renewPlan?->name ?? $planName;
    $price = (int) ($renewPlan?->price_monthly ?? 0);
    $priceLabel = $price > 0 ? '₹'.number_format($price / 100, $price % 100 ? 2 : 0) : null;
@endphp
@if($isTrial)
Your free trial ends soon

Your {{ $planName }} trial ends on {{ $dueDate }}. Choose a plan before then to keep your workspace running without interruption.

Choose a plan:
@else
Your plan is due for renewal

Your {{ $planName }} plan is paid until {{ $dueDate }}. Nothing is charged automatically, so please renew before that date to avoid any interruption.

Renewing early is safe: the new period starts when the current one ends, so you don't lose any days.@if($renewName !== $planName) Your plan changes to {{ $renewName }} on {{ $dueDate }}, so you renew at the {{ $renewName }} price.@endif @if($priceLabel) The {{ $renewName }} plan is {{ $priceLabel }}/month plus applicable taxes.@endif


Renew now:
@endif
{{ $billingUrl }}
