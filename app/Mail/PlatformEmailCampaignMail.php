<?php

namespace App\Mail;

use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlatformEmailCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PlatformEmailCampaign $campaign,
        public PlatformEmailCampaignRecipient $recipient
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->campaign->subject)
            ->view('emails.platform.campaign')
            ->with([
                'campaign' => $this->campaign,
                'recipient' => $this->recipient,
            ]);
    }
}
