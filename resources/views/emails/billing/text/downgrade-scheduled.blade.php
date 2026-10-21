@php
    $currentName = $currentPlan?->name ?? 'your current plan';
    $newName = $newPlan?->name ?? 'your new plan';
    $changeDate = $subscription->scheduled_change_at?->format('d M Y') ?? 'the end of your current plan period';
    $yearly = ($subscription->scheduled_billing_cycle ?? 'monthly') === 'yearly';
    $price = (int) (($yearly ? $newPlan?->price_yearly : $newPlan?->price_monthly) ?? 0);
    $priceLabel = $price > 0 ? 'Rs. '.number_format($price / 100, $price % 100 ? 2 : 0).($yearly ? '/year' : '/month') : null;
@endphp
Your plan will change to {{ $newName }} on {{ $changeDate }}

Nothing has been charged. You keep everything in {{ $currentName }} until {{ $changeDate }}. After that, your workspace moves to {{ $newName }}@if($priceLabel) and your next renewal is due at {{ $priceLabel }} plus applicable taxes @endif. We'll email a reminder before it's due; nothing is charged automatically.

Make sure your usage, team size and WhatsApp connections fit within the {{ $newName }} limits by then. Changed your mind? You can cancel the change from Billing at any time before {{ $changeDate }}.

Review or cancel the change:
{{ $billingUrl }}
