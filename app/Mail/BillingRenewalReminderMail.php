<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingRenewalReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function build(): self
    {
        $date = $this->subscription->current_period_end?->format('d M');
        $subject = $this->subscription->status === 'trialing'
            ? ('Your '.brand_name().' free trial ends').($date ? " on {$date}" : ' soon')
            : ('Your '.brand_name().' plan expires').($date ? " on {$date}" : ' soon').': renew to avoid interruption';

        return $this->subject($subject)
            ->view('emails.billing.renewal-reminder')
            ->text('emails.billing.text.renewal-reminder')
            ->with([
                'subscription' => $this->subscription,
                'billingUrl' => route('app.billing.index', ['tab' => 'plans']),
                'brand' => app(\App\Services\BrandingService::class)->getAll(),
            ]);
    }
}
