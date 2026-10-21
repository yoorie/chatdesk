<?php

namespace App\Services;

use App\Models\Account;
use App\Models\NotificationOutbox;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Throwable;

class NotificationOutboxService
{
    public function queueForNotification(mixed $notifiable, Notification $notification, string $channel = 'mail', ?Account $account = null, array $meta = []): NotificationOutbox
    {
        return NotificationOutbox::create([
            'account_id' => $account?->id ?? $this->resolveAccountId($notifiable),
            'notifiable_type' => is_object($notifiable) ? $notifiable::class : null,
            'notifiable_id' => is_object($notifiable) && method_exists($notifiable, 'getKey') ? (string) $notifiable->getKey() : null,
            'channel' => $channel,
            'notification_class' => $notification::class,
            'template_key' => class_basename($notification),
            'recipient' => $this->resolveRecipient($notifiable, $channel),
            'subject' => $this->resolveNotificationSubject($notifiable, $notification),
            'status' => 'queued',
            'queued_at' => now(),
            'meta' => $meta,
        ]);
    }

    public function queueForMail(string $recipient, string $templateKey, ?string $subject = null, ?Account $account = null, array $meta = []): NotificationOutbox
    {
        return NotificationOutbox::create([
            'account_id' => $account?->id,
            'channel' => 'mail',
            'template_key' => $templateKey,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => 'queued',
            'queued_at' => now(),
            'meta' => $meta,
        ]);
    }

    public function markRetrying(NotificationOutbox $outbox, ?string $reason = null, array $provider = []): void
    {
        $outbox->update([
            'status' => 'retrying',
            'attempts' => max(0, (int) $outbox->attempts) + 1,
            'last_attempt_at' => now(),
            'failure_reason' => $reason ? mb_substr($reason, 0, 2000) : null,
            'provider_code' => Arr::get($provider, 'code'),
            'provider_message_id' => Arr::get($provider, 'message_id'),
            'provider_response' => Arr::get($provider, 'response'),
        ]);
    }

    public function markSent(NotificationOutbox $outbox, array $provider = []): void
    {
        $outbox->update([
            'status' => 'sent',
            'attempts' => max(0, (int) $outbox->attempts) + 1,
            'last_attempt_at' => now(),
            'sent_at' => now(),
            'failed_at' => null,
            'failure_reason' => null,
            'provider_code' => Arr::get($provider, 'code'),
            'provider_message_id' => Arr::get($provider, 'message_id'),
            'provider_response' => Arr::get($provider, 'response'),
        ]);
    }

    public function markFailed(NotificationOutbox $outbox, ?string $reason = null, array $provider = []): void
    {
        $outbox->update([
            'status' => 'failed',
            'attempts' => max(0, (int) $outbox->attempts) + 1,
            'last_attempt_at' => now(),
            'failed_at' => now(),
            'failure_reason' => $reason ? mb_substr($reason, 0, 2000) : null,
            'provider_code' => Arr::get($provider, 'code'),
            'provider_message_id' => Arr::get($provider, 'message_id'),
            'provider_response' => Arr::get($provider, 'response'),
        ]);
    }

    protected function resolveRecipient(mixed $notifiable, string $channel): ?string
    {
        if (! is_object($notifiable)) {
            return null;
        }
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor($channel);
            if (is_array($route)) {
                return (string) ($route[0] ?? null);
            }

            return $route ? (string) $route : null;
        }

        return property_exists($notifiable, 'email') ? (string) ($notifiable->email ?? null) : null;
    }

    protected function resolveNotificationSubject(mixed $notifiable, Notification $notification): ?string
    {
        try {
            if (! method_exists($notification, 'toMail') || ! is_object($notifiable)) {
                return null;
            }
            $mailMessage = $notification->toMail($notifiable);
            if (! $mailMessage instanceof MailMessage) {
                return null;
            }

            return is_string($mailMessage->subject) ? $mailMessage->subject : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveAccountId(mixed $notifiable): ?int
    {
        if (! is_object($notifiable)) {
            return null;
        }
        if (property_exists($notifiable, 'account_id') && is_numeric($notifiable->account_id)) {
            return (int) $notifiable->account_id;
        }

        return null;
    }
}
