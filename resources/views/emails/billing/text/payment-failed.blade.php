Payment needs attention

@if(in_array($order->payment_method, ['bank', 'upi', 'manual'], true))
We couldn't confirm the payment receipt you uploaded for this invoice. Your plan has not been changed.
@else
Your payment attempt for this invoice didn't go through, so your plan has not been changed. If money was debited, your bank normally reverses it automatically within 5-7 working days. If you already completed the payment on a second attempt, you can ignore this email.
@endif

Reason: {{ $reason }}

@include('emails.billing.text.summary')

Open Billing:
{{ $billingUrl }}
