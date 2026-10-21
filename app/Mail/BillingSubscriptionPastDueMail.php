<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingSubscriptionPastDueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function build(): self
    {
        $isTrial = $this->subscription->trial_ends_at !== null;

        return $this->subject($isTrial ? ('Your '.brand_name().' free trial has ended') : ('Your '.brand_name().' plan has expired'))
            ->view('emails.billing.subscription-past-due')
            ->text('emails.billing.text.subscription-past-due')
            ->with([
                'subscription' => $this->subscription,
                'isTrial' => $isTrial,
                'billingUrl' => route('app.billing.index', ['tab' => 'plans']),
                'brand' => app(\App\Services\BrandingService::class)->getAll(),
            ]);
    }
}
