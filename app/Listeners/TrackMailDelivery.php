<?php

namespace App\Listeners;

use App\Models\NotificationOutbox;
use App\Services\NotificationOutboxService;
use Illuminate\Mail\Events\MessageSent;

class TrackMailDelivery
{
    public function __construct(
        protected NotificationOutboxService $outboxService
    ) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $headers = $message->getHeaders();
        $outboxHeader = $headers->has('X-App-Outbox-Id')
            ? $headers->get('X-App-Outbox-Id')->getBodyAsString()
            : null;

        if (! $outboxHeader || ! is_numeric($outboxHeader)) {
            return;
        }

        $outbox = NotificationOutbox::find((int) $outboxHeader);
        if (! $outbox || ! in_array($outbox->status, ['queued', 'retrying'], true)) {
            return;
        }

        $providerMessageId = method_exists($event->sent, 'getMessageId')
            ? (string) $event->sent->getMessageId()
            : null;

        $debug = method_exists($event->sent, 'getDebug') ? $event->sent->getDebug() : null;
        $providerResponse = is_array($debug) ? $debug : (is_string($debug) ? ['raw' => mb_substr($debug, 0, 2000)] : null);

        $this->outboxService->markSent($outbox, [
            'message_id' => $providerMessageId,
            'response' => $providerResponse,
        ]);
    }
}
