<?php

namespace App\Listeners;

use App\Models\NotificationOutbox;
use App\Services\NotificationOutboxService;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Arr;

class TrackNotificationDelivery
{
    public function __construct(
        protected NotificationOutboxService $outboxService
    ) {}

    public function handleSent(NotificationSent $event): void
    {
        $outbox = $this->resolveOutbox($event->notification, $event->notifiable, $event->channel);
        if (! $outbox) {
            return;
        }

        $provider = $this->extractProviderInfo($event->response);
        $this->outboxService->markSent($outbox, $provider);
    }

    public function handleFailed(NotificationFailed $event): void
    {
        $outbox = $this->resolveOutbox($event->notification, $event->notifiable, $event->channel);
        if (! $outbox) {
            return;
        }

        $tries = (int) ($event->notification->tries ?? 1);
        $nextAttempt = ((int) $outbox->attempts) + 1;
        $reason = Arr::get($event->data, 'error') ?: Arr::get($event->data, 'message') ?: Arr::get($event->data, 'exception');
        $provider = $this->extractProviderInfo(Arr::get($event->data, 'response'));

        if ($nextAttempt < max(1, $tries)) {
            $this->outboxService->markRetrying($outbox, (string) $reason, $provider);

            return;
        }

        $this->outboxService->markFailed($outbox, (string) $reason, $provider);
    }

    protected function resolveOutbox(object $notification, object $notifiable, string $channel): ?NotificationOutbox
    {
        $outboxId = isset($notification->__outbox_id) ? (int) $notification->__outbox_id : null;
        if ($outboxId) {
            return NotificationOutbox::find($outboxId);
        }

        $notifiableType = $notifiable::class;
        $notifiableId = method_exists($notifiable, 'getKey') ? (string) $notifiable->getKey() : null;
        if (! $notifiableId) {
            return null;
        }

        return NotificationOutbox::query()
            ->where('notification_class', $notification::class)
            ->where('channel', $channel)
            ->where('notifiable_type', $notifiableType)
            ->where('notifiable_id', $notifiableId)
            ->whereIn('status', ['queued', 'retrying'])
            ->latest('id')
            ->first();
    }

    protected function extractProviderInfo(mixed $response): array
    {
        if (! $response) {
            return [];
        }

        $result = [
            'code' => null,
            'message_id' => null,
            'response' => null,
        ];

        if (is_object($response) && method_exists($response, 'getMessageId')) {
            $result['message_id'] = (string) $response->getMessageId();
        }

        if (is_object($response) && method_exists($response, 'getDebug')) {
            $debug = $response->getDebug();
            if (is_array($debug)) {
                $result['response'] = $debug;
                $result['code'] = (string) (Arr::get($debug, 'code') ?? Arr::get($debug, 'status') ?? '');
            }
        } elseif (is_array($response)) {
            $result['response'] = $response;
            $result['code'] = (string) (Arr::get($response, 'code') ?? Arr::get($response, 'status') ?? '');
            $result['message_id'] = $result['message_id'] ?: (string) (Arr::get($response, 'message_id') ?? Arr::get($response, 'id') ?? '');
        } elseif (is_string($response)) {
            $result['response'] = ['raw' => mb_substr($response, 0, 2000)];
        }

        if ($result['code'] === '') {
            $result['code'] = null;
        }
        if ($result['message_id'] === '') {
            $result['message_id'] = null;
        }

        return $result;
    }
}
