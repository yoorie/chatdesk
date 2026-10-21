<?php

namespace App\Jobs;

use App\Models\AccountWebhookDelivery;
use App\Models\AccountWebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWorkspaceWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $endpointId,
        public array $payload
    ) {}

    public function handle(): void
    {
        $endpoint = AccountWebhookEndpoint::find($this->endpointId);

        if (! $endpoint || ! $endpoint->is_enabled) {
            return;
        }

        $delivery = AccountWebhookDelivery::firstOrCreate(
            [
                'account_webhook_endpoint_id' => $endpoint->id,
                'delivery_id' => (string) ($this->payload['id'] ?? ''),
            ],
            [
                'account_id' => $endpoint->account_id,
                'event' => (string) ($this->payload['event'] ?? ''),
                'url' => $endpoint->url,
                'payload' => $this->payload,
            ]
        );

        $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES) ?: '';
        $headers = [
            'User-Agent' => 'App-Webhooks/1.0',
            'X-App-Event' => (string) ($this->payload['event'] ?? ''),
            'X-App-Delivery' => (string) ($this->payload['id'] ?? ''),
        ];

        if ($endpoint->secret) {
            $headers['X-App-Signature'] = 'sha256='.hash_hmac('sha256', $body, $endpoint->secret);
        }

        $deliveryRecorded = false;

        try {
            $started = microtime(true);
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders($headers)
                ->post($endpoint->url, $this->payload);
            $duration = (int) round((microtime(true) - $started) * 1000);

            $endpoint->update([
                'last_tested_at' => now(),
                'last_status' => $response->status(),
                'last_error' => $response->successful() ? null : substr($response->body(), 0, 1000),
            ]);

            $delivery->forceFill([
                'account_id' => $endpoint->account_id,
                'event' => (string) ($this->payload['event'] ?? ''),
                'url' => $endpoint->url,
                'status' => $response->status(),
                'attempts' => $delivery->attempts + 1,
                'duration_ms' => $duration,
                'payload' => $this->payload,
                'response_body' => substr($response->body(), 0, 2000) ?: null,
                'error' => $response->successful() ? null : substr($response->body(), 0, 1000),
                'delivered_at' => $response->successful() ? now() : null,
            ])->save();
            $deliveryRecorded = true;

            if (! $response->successful()) {
                throw new \RuntimeException('Workspace webhook returned HTTP '.$response->status());
            }
        } catch (\Throwable $e) {
            $endpoint->update([
                'last_tested_at' => now(),
                'last_status' => null,
                'last_error' => $e->getMessage(),
            ]);

            if (! $deliveryRecorded) {
                $delivery->forceFill([
                    'account_id' => $endpoint->account_id,
                    'event' => (string) ($this->payload['event'] ?? ''),
                    'url' => $endpoint->url,
                    'status' => null,
                    'attempts' => $delivery->attempts + 1,
                    'payload' => $this->payload,
                    'error' => $e->getMessage(),
                ])->save();
            }

            Log::warning('Workspace webhook delivery failed', [
                'endpoint_id' => $endpoint->id,
                'account_id' => $endpoint->account_id,
                'event' => $this->payload['event'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
