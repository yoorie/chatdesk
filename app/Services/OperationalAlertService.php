<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OperationalAlertService
{
    public function send(string $eventKey, string $title, array $context = [], string $severity = 'warning'): void
    {
        $dedupeKey = 'ops-alert:'.sha1($eventKey.'|'.($context['scope'] ?? 'global'));
        $ttlMinutes = max(1, (int) PlatformSetting::get('alerts.dedupe_minutes', 15));
        if (! cache()->add($dedupeKey, now()->timestamp, now()->addMinutes($ttlMinutes))) {
            return;
        }

        $payload = [
            'event' => $eventKey,
            'severity' => $severity,
            'title' => $title,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ];

        $this->sendEmailAlert($payload);
        $this->sendWebhookAlert($payload);
    }

    protected function sendEmailAlert(array $payload): void
    {
        $to = PlatformSetting::get('alerts.email_to')
            ?: PlatformSetting::get('branding.support_email')
            ?: PlatformSetting::get('general.support_email');
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $subject = '['.brand_name()." Alert] {$payload['title']}";
        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            Mail::raw((string) $body, function ($message) use ($to, $subject) {
                $message->to((string) $to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send operational email alert', ['error' => $e->getMessage()]);
        }
    }

    protected function sendWebhookAlert(array $payload): void
    {
        $webhookUrl = PlatformSetting::get('alerts.webhook_url');
        $slackWebhook = PlatformSetting::get('alerts.slack_webhook_url');

        if ($webhookUrl) {
            try {
                Http::timeout(8)->post((string) $webhookUrl, $payload);
            } catch (\Throwable $e) {
                Log::warning('Failed to send operational webhook alert', ['error' => $e->getMessage()]);
            }
        }

        if ($slackWebhook) {
            try {
                Http::timeout(8)->post((string) $slackWebhook, [
                    'text' => "{$payload['title']} ({$payload['severity']})\n".json_encode($payload['context'], JSON_UNESCAPED_SLASHES),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to send Slack operational alert', ['error' => $e->getMessage()]);
            }
        }
    }
}
