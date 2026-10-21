@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
    $currentName = $currentPlan?->name ?? 'your current plan';
    $newName = $newPlan?->name ?? 'your new plan';
    $changeDate = $subscription->scheduled_change_at?->format('d M Y') ?? 'the end of your current plan period';
    $yearly = ($subscription->scheduled_billing_cycle ?? 'monthly') === 'yearly';
    $price = (int) (($yearly ? $newPlan?->price_yearly : $newPlan?->price_monthly) ?? 0);
    $priceLabel = $price > 0 ? '₹'.number_format($price / 100, $price % 100 ? 2 : 0).($yearly ? '/year' : '/month') : null;
@endphp
<x-emails.billing.layout title="Plan change scheduled">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">Your plan will change to {{ $newName }} on {{ $changeDate }}</h1>
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Nothing has been charged. You keep everything in {{ $currentName }} until <strong>{{ $changeDate }}</strong>. After that, your workspace moves to {{ $newName }}@if($priceLabel) and your next renewal is due at {{ $priceLabel }} plus applicable taxes@endif. We'll email a reminder before it's due; nothing is charged automatically.</p>
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Make sure your usage, team size and WhatsApp connections fit within the {{ $newName }} limits by then. Changed your mind? You can cancel the change from Billing at any time before {{ $changeDate }}.</p>
    <p style="margin:22px 0 0;">
        <a href="{{ $billingUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">Review or cancel the change</a>
    </p>
</x-emails.billing.layout>
