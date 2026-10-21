<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BillingDowngradeScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function build(): self
    {
        $newPlan = $this->subscription->scheduledPlan;
        $date = $this->subscription->scheduled_change_at?->format('d M');

        return $this->subject(('Your '.brand_name().' plan will change to ').($newPlan?->name ?? 'a new plan').($date ? " on {$date}" : ''))
            ->view('emails.billing.downgrade-scheduled')
            ->text('emails.billing.text.downgrade-scheduled')
            ->with([
                'subscription' => $this->subscription,
                'currentPlan' => $this->subscription->plan,
                'newPlan' => $newPlan,
                'billingUrl' => route('app.billing.index', ['tab' => 'overview']),
                'brand' => app(\App\Services\BrandingService::class)->getAll(),
            ]);
    }
}
