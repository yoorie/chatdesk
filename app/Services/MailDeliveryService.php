<?php

namespace App\Services;

use App\Models\Account;
use App\Models\NotificationOutbox;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailDeliveryService
{
    public function __construct(
        protected NotificationOutboxService $outboxService
    ) {}

    public function sendMailable(string $recipient, Mailable $mailable, ?string $mailer = null, ?Account $account = null, array $meta = []): NotificationOutbox
    {
        $subject = $mailable->subject ?? null;
        $outbox = $this->outboxService->queueForMail(
            recipient: $recipient,
            templateKey: class_basename($mailable),
            subject: is_string($subject) ? $subject : null,
            account: $account,
            meta: $meta
        );

        $mailable->withSymfonyMessage(function ($message) use ($outbox) {
            $message->getHeaders()->addTextHeader('X-App-Outbox-Id', (string) $outbox->id);
        });

        try {
            $mail = $mailer ? Mail::mailer($mailer) : Mail::mailer();
            $mail->to($recipient)->send($mailable);
        } catch (Throwable $exception) {
            $this->outboxService->markFailed($outbox, $exception->getMessage());
            throw $exception;
        }

        return $outbox->fresh();
    }
}
