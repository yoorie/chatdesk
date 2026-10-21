<?php

namespace App\Mail;

use App\Models\AccountInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public AccountInvitation $invitation) {}

    public function build(): self
    {
        $inviteUrl = route('register', [
            'invite' => $this->invitation->token,
            'email' => $this->invitation->email]);

        return $this->subject('You have been invited to join an account')
            ->view('emails.account-invitation')
            ->with([
                'invitation' => $this->invitation,
                'inviteUrl' => $inviteUrl]);
    }
}
