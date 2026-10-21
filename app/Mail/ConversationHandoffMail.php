<?php

namespace App\Mail;

use App\Models\AppNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConversationHandoffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AppNotification $notification) {}

    public function build(): self
    {
        return $this->subject($this->notification->title ?: 'Chat needs human attention')
            ->view('emails.conversation-handoff')
            ->with([
                'notification' => $this->notification,
                'account' => $this->notification->account,
                'actionUrl' => $this->notification->action_url ?: route('app.whatsapp.conversations.index'),
                'brand' => app(\App\Services\BrandingService::class)->getAll(),
            ]);
    }
}
