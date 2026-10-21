<?php

namespace App\Http\Controllers;

use App\Models\AccountApiKey;
use App\Models\AccountApiRequestLog;
use App\Models\AccountWebhookDelivery;
use App\Models\AccountWebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DeveloperController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        if (in_array($request->query('tab'), ['invoices', 'transactions'], true)) {
            return redirect()->route('app.billing.index', ['tab' => $request->query('tab') === 'invoices' ? 'invoices' : 'payment']);
        }

        $webhookDeliveryQuery = AccountWebhookDelivery::where('account_id', $account->id);

        $webhookDeliveries = (clone $webhookDeliveryQuery)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (AccountWebhookDelivery $delivery) => [
                'id' => $delivery->delivery_id,
                'event' => $delivery->event,
                'url' => $delivery->url,
                'status' => $delivery->status ?? 0,
                'attempts' => $delivery->attempts,
                'duration' => $delivery->duration_ms ?? 0,
                'time' => $delivery->created_at?->toIso8601String(),
                'error' => $delivery->error,
            ]);

        $requestLogQuery = AccountApiRequestLog::where('account_id', $account->id);
        $requestLogs = (clone $requestLogQuery)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (AccountApiRequestLog $log) => [
                'id' => 'req_'.$log->id,
                'method' => $log->method,
                'path' => $log->path,
                'status' => $log->status,
                'duration' => $log->duration_ms,
                'ip' => $log->ip,
                'time' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Developer/Index', [
            'webhooks' => [
                'events' => $this->webhookEvents(),
                'deliveries' => $webhookDeliveries,
                'endpoints' => AccountWebhookEndpoint::where('account_id', $account->id)
                    ->orderByDesc('is_enabled')
                    ->orderByDesc('updated_at')
                    ->get()
                    ->map(fn (AccountWebhookEndpoint $endpoint) => [
                        'id' => $endpoint->id,
                        'url' => $endpoint->url,
                        'events' => $endpoint->events ?: [],
                        'is_enabled' => (bool) $endpoint->is_enabled,
                        'has_secret' => filled($endpoint->secret),
                        'last_tested_at' => $endpoint->last_tested_at?->toIso8601String(),
                        'last_status' => $endpoint->last_status,
                        'last_error' => $endpoint->last_error,
                        'created_at' => $endpoint->created_at?->toIso8601String(),
                    ]),
            ],
            'requestLogs' => $requestLogs,
            'api' => [
                'base_url' => rtrim((string) config('app.url'), '/').'/api/v1/whatsapp',
                'rate_limit' => '60 requests/minute',
                'endpoints' => $this->apiEndpointGroups(),
            ],
            'keys' => AccountApiKey::where('account_id', $account->id)
                ->orderByRaw('revoked_at is not null')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (AccountApiKey $key) => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'prefix' => $key->token_prefix,
                    'lastUsed' => $key->last_used_at?->toIso8601String(),
                    'lastUsedIp' => $key->last_used_ip,
                    'created' => $key->created_at?->toIso8601String(),
                    'revokedAt' => $key->revoked_at?->toIso8601String(),
                    'scopes' => $key->scopes ?: [],
                ]),
            'newApiKey' => $request->session()->pull('new_api_key'),
            'availableScopes' => $this->availableScopes(),
        ]);
    }

    public function storeKey(Request $request): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        app(\App\Services\WorkspacePermissionService::class)->assertOwner($request->user(), $account);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', Rule::in(array_column($this->availableScopes(), 'id'))],
        ]);

        [, $token] = AccountApiKey::issue($account, $validated['name'], $validated['scopes']);
        app(\App\Services\AppNotificationService::class)->auditDestructive(
            'api_key_created',
            "API key '{$validated['name']}' created",
            $request->user(),
            $account,
            null,
            ['scopes' => $validated['scopes']],
            $request
        );

        return back()
            ->with('success', 'API key created. Copy it now; it will not be shown again.')
            ->with('new_api_key', $token);
    }

    public function destroyKey(Request $request, AccountApiKey $key): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless((int) $key->account_id === (int) $account->id, 404);
        app(\App\Services\WorkspacePermissionService::class)->assertOwner($request->user(), $account);

        $key->forceFill(['revoked_at' => now()])->save();
        app(\App\Services\AppNotificationService::class)->auditDestructive(
            'api_key_revoked',
            "API key '{$key->name}' revoked",
            $request->user(),
            $account,
            $key,
            ['prefix' => $key->token_prefix],
            $request
        );

        return back()->with('success', 'API key revoked.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        app(\App\Services\WorkspacePermissionService::class)->assertOwner($request->user(), $account);
        $validated = $this->validateWebhook($request);

        AccountWebhookEndpoint::create([
            'account_id' => $account->id,
            ...$validated,
        ]);

        return back()->with('success', 'Webhook endpoint added.');
    }

    public function updateWebhook(Request $request, AccountWebhookEndpoint $endpoint): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless((int) $endpoint->account_id === (int) $account->id, 404);
        app(\App\Services\WorkspacePermissionService::class)->assertOwner($request->user(), $account);

        $validated = $this->validateWebhook($request);
        if (($validated['secret'] ?? null) === null || $validated['secret'] === '') {
            unset($validated['secret']);
        }

        $endpoint->update($validated);

        return back()->with('success', 'Webhook endpoint updated.');
    }

    public function testWebhook(Request $request, AccountWebhookEndpoint $endpoint): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless((int) $endpoint->account_id === (int) $account->id, 404);
        app(\App\Services\WorkspacePermissionService::class)->assertOwner($request->user(), $account);

        $deliveryId = 'evt_test_'.Str::uuid();
        $payload = [
            'id' => $deliveryId,
            'event' => 'webhook.test',
            'account_id' => $account->id,
            'created_at' => now()->toIso8601String(),
            'data' => [
                'message' => (brand_name().' developer webhook test'),
            ],
        ];

        try {
            $started = microtime(true);
            $response = Http::timeout(8)
                ->withHeaders($this->webhookHeaders($endpoint, $payload))
                ->post($endpoint->url, $payload);
            $duration = (int) round((microtime(true) - $started) * 1000);

            $endpoint->update([
                'last_tested_at' => now(),
                'last_status' => $response->status(),
                'last_error' => $response->successful() ? null : substr($response->body(), 0, 1000),
            ]);
            AccountWebhookDelivery::create([
                'account_id' => $account->id,
                'account_webhook_endpoint_id' => $endpoint->id,
                'delivery_id' => $deliveryId,
                'event' => 'webhook.test',
                'url' => $endpoint->url,
                'status' => $response->status(),
                'attempts' => 1,
                'duration_ms' => $duration,
                'payload' => $payload,
                'response_body' => substr($response->body(), 0, 2000) ?: null,
                'error' => $response->successful() ? null : substr($response->body(), 0, 1000),
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            return back()->with($response->successful() ? 'success' : 'warning', $response->successful()
                ? 'Webhook test delivered.'
                : 'Webhook test completed with a non-success response.');
        } catch (\Throwable $e) {
            $endpoint->update([
                'last_tested_at' => now(),
                'last_status' => null,
                'last_error' => $e->getMessage(),
            ]);
            AccountWebhookDelivery::create([
                'account_id' => $account->id,
                'account_webhook_endpoint_id' => $endpoint->id,
                'delivery_id' => $deliveryId,
                'event' => 'webhook.test',
                'url' => $endpoint->url,
                'status' => null,
                'attempts' => 1,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Webhook test failed: '.\App\Support\CustomerMessage::humanize($e->getMessage(), 'Your server did not respond as expected.'));
        }
    }

    public function destroyWebhook(Request $request, AccountWebhookEndpoint $endpoint): RedirectResponse
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_unless((int) $endpoint->account_id === (int) $account->id, 404);
        app(\App\Services\WorkspacePermissionService::class)->assertOwner($request->user(), $account);

        $endpoint->delete();
        app(\App\Services\AppNotificationService::class)->auditDestructive(
            'webhook_endpoint_deleted',
            "Webhook endpoint {$endpoint->url} deleted",
            $request->user(),
            $account,
            $endpoint,
            [],
            $request
        );

        return back()->with('success', 'Webhook endpoint removed.');
    }

    private function validateWebhook(Request $request): array
    {
        return $request->validate([
            'url' => ['required', 'url', 'max:500'],
            'secret' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', Rule::in(array_column($this->webhookEvents(), 'id'))],
            'is_enabled' => ['nullable', 'boolean'],
        ]) + ['is_enabled' => true];
    }

    private function webhookHeaders(AccountWebhookEndpoint $endpoint, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = [
            'User-Agent' => 'App-Webhooks/1.0',
            'X-App-Event' => $payload['event'],
            'X-App-Delivery' => $payload['id'],
        ];

        if ($endpoint->secret) {
            $headers['X-App-Signature'] = 'sha256='.hash_hmac('sha256', $body ?: '', $endpoint->secret);
        }

        return $headers;
    }


    private function webhookEvents(): array
    {
        return [
            ['id' => 'message.sent', 'label' => 'message.sent', 'desc' => 'Outbound message accepted by Meta'],
            ['id' => 'message.received', 'label' => 'message.received', 'desc' => 'Inbound message from a contact'],
            ['id' => 'message.delivered', 'label' => 'message.delivered', 'desc' => 'Outbound message delivered'],
            ['id' => 'message.read', 'label' => 'message.read', 'desc' => 'Outbound message read'],
            ['id' => 'template.status_updated', 'label' => 'template.status_updated', 'desc' => 'Template approval or rejection update'],
            ['id' => 'connection.error', 'label' => 'connection.error', 'desc' => 'Webhook or WABA connection failure'],
        ];
    }

    private function availableScopes(): array
    {
        return [
            ['id' => 'connections:read', 'label' => 'Read WABA connections'],
            ['id' => 'templates:read', 'label' => 'Read templates'],
            ['id' => 'templates:sync', 'label' => 'Sync templates'],
            ['id' => 'conversations:read', 'label' => 'Read conversations'],
            ['id' => 'messages:write', 'label' => 'Send messages'],
            ['id' => '*', 'label' => 'Full API access'],
        ];
    }

    private function apiEndpointGroups(): array
    {
        return [
            [
                'id' => 'connections',
                'label' => 'Connections',
                'endpoints' => [
                    [
                        'id' => 'list-connections',
                        'method' => 'GET',
                        'path' => '/connections',
                        'summary' => 'List WABA accounts',
                        'description' => 'Returns WhatsApp Business API connections available to the workspace.',
                        'params' => [],
                        'example' => "curl {$this->apiUrl('/connections')} -H \"Authorization: Bearer wfy_live_sk_xxx\"",
                        'response' => ('{ "data": [{ "id": 1, "name": "'.brand_name().' Business", "is_active": true }] }'),
                    ],
                ],
            ],
            [
                'id' => 'messages',
                'label' => 'Messages',
                'endpoints' => [
                    [
                        'id' => 'send-text',
                        'method' => 'POST',
                        'path' => '/messages/text',
                        'summary' => 'Send text message',
                        'description' => 'Send a session text message through the workspace WABA account.',
                        'params' => [
                            ['name' => 'to', 'type' => 'string', 'required' => true, 'desc' => 'E.164 WhatsApp phone number'],
                            ['name' => 'message', 'type' => 'string', 'required' => true, 'desc' => 'Message body'],
                            ['name' => 'connection_id', 'type' => 'integer', 'required' => false, 'desc' => 'Optional WABA connection ID'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/messages/text')} \\\n  -H \"Authorization: Bearer wfy_live_sk_xxx\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"to\":\"+919876543210\",\"message\":\"Hello from our app\"}'",
                        'response' => '{ "data": { "message_id": 42, "status": "sent" } }',
                    ],
                    [
                        'id' => 'send-template',
                        'method' => 'POST',
                        'path' => '/messages/template',
                        'summary' => 'Send template message',
                        'description' => 'Send an approved Meta template with ordered variables.',
                        'params' => [
                            ['name' => 'to', 'type' => 'string', 'required' => true, 'desc' => 'E.164 WhatsApp phone number'],
                            ['name' => 'template_id', 'type' => 'integer', 'required' => false, 'desc' => 'Template ID'],
                            ['name' => 'template_name', 'type' => 'string', 'required' => false, 'desc' => 'Template name fallback'],
                            ['name' => 'variables', 'type' => 'array', 'required' => false, 'desc' => 'Template body variables'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/messages/template')} \\\n  -H \"Authorization: Bearer wfy_live_sk_xxx\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"to\":\"+919876543210\",\"template_name\":\"order_update\",\"language\":\"en_US\",\"variables\":[\"Prashant\",\"ORD-1042\"]}'",
                        'response' => '{ "data": { "message_id": 43, "status": "sent" } }',
                    ],
                    [
                        'id' => 'send-media',
                        'method' => 'POST',
                        'path' => '/messages/media',
                        'summary' => 'Send media message',
                        'description' => 'Send image, video, audio, or document media using a public URL.',
                        'params' => [
                            ['name' => 'to', 'type' => 'string', 'required' => true, 'desc' => 'E.164 WhatsApp phone number'],
                            ['name' => 'type', 'type' => 'string', 'required' => true, 'desc' => 'image, video, audio, or document'],
                            ['name' => 'link', 'type' => 'url', 'required' => true, 'desc' => 'Public media URL'],
                            ['name' => 'caption', 'type' => 'string', 'required' => false, 'desc' => 'Caption for supported media'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/messages/media')} \\\n  -H \"Authorization: Bearer wfy_live_sk_xxx\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"to\":\"+919876543210\",\"type\":\"image\",\"link\":\"https://example.com/product.jpg\",\"caption\":\"New arrival\"}'",
                        'response' => '{ "data": { "message_id": 44, "status": "sent" } }',
                    ],
                    [
                        'id' => 'send-location',
                        'method' => 'POST',
                        'path' => '/messages/location',
                        'summary' => 'Send location message',
                        'description' => 'Send a WhatsApp location pin, matching the inbox location action.',
                        'params' => [
                            ['name' => 'to', 'type' => 'string', 'required' => true, 'desc' => 'E.164 WhatsApp phone number'],
                            ['name' => 'latitude', 'type' => 'number', 'required' => true, 'desc' => 'Latitude'],
                            ['name' => 'longitude', 'type' => 'number', 'required' => true, 'desc' => 'Longitude'],
                            ['name' => 'name', 'type' => 'string', 'required' => false, 'desc' => 'Location label'],
                            ['name' => 'address', 'type' => 'string', 'required' => false, 'desc' => 'Readable address'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/messages/location')} \\\n  -H \"Authorization: Bearer wfy_live_sk_xxx\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"to\":\"+919876543210\",\"latitude\":28.6139,\"longitude\":77.2090,\"name\":\"Delhi showroom\"}'",
                        'response' => '{ "data": { "message_id": 45, "status": "sent" } }',
                    ],
                    [
                        'id' => 'send-list',
                        'method' => 'POST',
                        'path' => '/messages/list',
                        'summary' => 'Send interactive list',
                        'description' => 'Send an active list created under Templates > Interactive lists.',
                        'params' => [
                            ['name' => 'to', 'type' => 'string', 'required' => true, 'desc' => 'E.164 WhatsApp phone number'],
                            ['name' => 'list_id', 'type' => 'integer', 'required' => true, 'desc' => (brand_name().' interactive list ID')],
                            ['name' => 'connection_id', 'type' => 'integer', 'required' => false, 'desc' => 'Optional WABA connection ID'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/messages/list')} \\\n  -H \"Authorization: Bearer wfy_live_sk_xxx\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"to\":\"+919876543210\",\"list_id\":7}'",
                        'response' => '{ "data": { "message_id": 46, "status": "sent" } }',
                    ],
                    [
                        'id' => 'send-buttons',
                        'method' => 'POST',
                        'path' => '/messages/buttons',
                        'summary' => 'Send reply buttons',
                        'description' => 'Send a WhatsApp interactive button message with up to three quick reply buttons.',
                        'params' => [
                            ['name' => 'to', 'type' => 'string', 'required' => true, 'desc' => 'E.164 WhatsApp phone number'],
                            ['name' => 'body_text', 'type' => 'string', 'required' => true, 'desc' => 'Message body'],
                            ['name' => 'buttons', 'type' => 'array', 'required' => true, 'desc' => '1-3 buttons with text and optional id'],
                            ['name' => 'header_text', 'type' => 'string', 'required' => false, 'desc' => 'Optional header'],
                            ['name' => 'footer_text', 'type' => 'string', 'required' => false, 'desc' => 'Optional footer'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/messages/buttons')} \\\n  -H \"Authorization: Bearer wfy_live_sk_xxx\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"to\":\"+919876543210\",\"body_text\":\"Choose an option\",\"buttons\":[{\"id\":\"support\",\"text\":\"Support\"},{\"id\":\"sales\",\"text\":\"Sales\"}]}'",
                        'response' => '{ "data": { "message_id": 47, "status": "sent" } }',
                    ],
                ],
            ],
            [
                'id' => 'templates',
                'label' => 'Templates',
                'endpoints' => [
                    [
                        'id' => 'list-templates',
                        'method' => 'GET',
                        'path' => '/templates',
                        'summary' => 'List templates',
                        'description' => 'Paginated template list with optional status and connection filters.',
                        'params' => [
                            ['name' => 'status', 'type' => 'string', 'required' => false, 'desc' => 'approved, pending, rejected'],
                            ['name' => 'connection_id', 'type' => 'integer', 'required' => false, 'desc' => 'WABA connection ID'],
                        ],
                        'example' => "curl {$this->apiUrl('/templates?status=approved')} -H \"Authorization: Bearer wfy_live_sk_xxx\"",
                        'response' => '{ "data": { "data": [] } }',
                    ],
                    [
                        'id' => 'sync-templates',
                        'method' => 'POST',
                        'path' => '/templates/sync',
                        'summary' => 'Sync templates from Meta',
                        'description' => 'Refreshes local template status and metadata from Meta.',
                        'params' => [
                            ['name' => 'connection_id', 'type' => 'integer', 'required' => true, 'desc' => 'WABA connection ID'],
                        ],
                        'example' => "curl -X POST {$this->apiUrl('/templates/sync')} -H \"Authorization: Bearer wfy_live_sk_xxx\" -d '{\"connection_id\":1}'",
                        'response' => '{ "data": { "total": 12, "updated": 12 } }',
                    ],
                ],
            ],
            [
                'id' => 'conversations',
                'label' => 'Conversations',
                'endpoints' => [
                    [
                        'id' => 'list-conversations',
                        'method' => 'GET',
                        'path' => '/conversations',
                        'summary' => 'List conversations',
                        'description' => 'Returns recent workspace conversations with contact and WABA metadata.',
                        'params' => [
                            ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'desc' => 'Items per page'],
                        ],
                        'example' => "curl {$this->apiUrl('/conversations')} -H \"Authorization: Bearer wfy_live_sk_xxx\"",
                        'response' => '{ "data": { "data": [] } }',
                    ],
                ],
            ],
        ];
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/whatsapp'.$path;
    }
}
