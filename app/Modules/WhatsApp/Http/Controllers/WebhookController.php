<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Modules\WhatsApp\Exceptions\WebhookEventLockedException;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Services\WebhookProcessor;
use App\Modules\WhatsApp\Services\WebhookSignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookProcessor $webhookProcessor
    ) {
        // Disable CSRF for webhook endpoints
        $this->middleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->except(['verify', 'receive']);
    }

    public function verifyCentral(Request $request)
    {
        $mode = $request->query('hub.mode')
            ?? $request->query('hub_mode')
            ?? $request->input('hub.mode')
            ?? $request->input('hub_mode');
        $mode = is_string($mode) ? trim($mode) : $mode;

        $token = $request->query('hub.verify_token')
            ?? $request->query('hub_verify_token')
            ?? $request->input('hub.verify_token')
            ?? $request->input('hub_verify_token');
        $token = is_string($token) ? trim($token) : $token;

        $challenge = $request->query('hub.challenge')
            ?? $request->query('hub_challenge')
            ?? $request->input('hub.challenge')
            ?? $request->input('hub_challenge');
        $challenge = is_string($challenge) ? trim($challenge) : $challenge;

        $expectedToken = $this->centralVerifyToken();

        Log::channel('whatsapp')->info('Central webhook verification attempt', [
            'mode' => $mode,
            'token_valid' => is_string($token) && hash_equals($expectedToken, $token),
            'has_challenge' => ! empty($challenge),
        ]);

        if ($mode === 'subscribe' && is_string($token) && hash_equals($expectedToken, $token) && ! empty($challenge)) {
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response()->json([
            'success' => false,
            'error' => 'Invalid webhook verification request',
        ], 403);
    }

    public function receiveCentral(Request $request)
    {
        $connection = $this->resolveConnectionFromPayload($request->all());

        // WABA-level events (message_template_status_update, template_category_update,
        // account_update, ...) carry no metadata.phone_number_id; route them by the
        // WABA ID in entry[].id to every active connection on that WABA.
        if (! $connection && ! $this->extractPhoneNumberId($request->all())) {
            $wabaConnections = $this->resolveConnectionsByWaba($request->all());
            if ($wabaConnections->isNotEmpty()) {
                $response = null;
                foreach ($wabaConnections as $wabaConnection) {
                    $result = $this->receive($request, $wabaConnection);
                    $response ??= $result;
                }

                return $response;
            }
        }

        if (! $connection) {
            $phoneNumberId = $this->extractPhoneNumberId($request->all());

            Log::channel('whatsapp')->warning('Central webhook could not resolve connection', [
                'correlation_id' => $request->attributes->get('webhook_correlation_id'),
                'phone_number_id' => $phoneNumberId,
            ]);

            if ($phoneNumberId) {
                app(\App\Services\AppNotificationService::class)->platform(
                    'unresolved_whatsapp_webhook',
                    'Unmatched WhatsApp webhook',
                    "Meta sent a webhook for phone number ID {$phoneNumberId}, but no active connection matches it.",
                    'warning',
                    route('platform.system-health'),
                    [
                        'phone_number_id' => $phoneNumberId,
                        'correlation_id' => $request->attributes->get('webhook_correlation_id'),
                        'dedupe_key' => 'phone_number_'.$phoneNumberId,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'status' => 'ignored',
                'error' => 'Connection not found for webhook phone number ID',
                'correlation_id' => $request->attributes->get('webhook_correlation_id'),
            ], 200);
        }

        return $this->receive($request, $connection);
    }

    /**
     * Verify webhook endpoint (GET).
     */
    public function verify(Request $request, WhatsAppConnection $connection)
    {
        $correlationId = $request->attributes->get('webhook_correlation_id', Str::uuid()->toString());

        try {
            // Connection is already resolved by route model binding
            Log::info('[Meta-WhatsApp-Webhook] GET verify called', [
                'connection_id' => $connection->id,
                'connection_slug' => $connection->slug,
                'path' => $request->path(),
            ]);
            Log::channel('whatsapp')->info('WebhookController::verify called', [
                'connection_id' => $connection->id,
                'connection_slug' => $connection->slug,
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
            ]);

            // Rate limit webhook verification
            $key = 'webhook-verify-'.$connection->id.'-'.$request->ip();
            if (RateLimiter::tooManyAttempts($key, 10)) {
                Log::channel('whatsapp')->warning('Webhook verification rate limited', [
                    'connection_id' => $connection->id,
                    'ip' => $request->ip()]);
                abort(429, 'Too many requests');
            }
            RateLimiter::hit($key, 60);

            // Meta sends parameters as query string: ?hub.mode=subscribe&hub.verify_token=xxx&hub.challenge=xxx
            // Also check for underscore format: hub_mode, hub_verify_token, hub_challenge
            $mode = $request->query('hub.mode')
                ?? $request->query('hub_mode')
                ?? $request->input('hub.mode')
                ?? $request->input('hub_mode');
            $mode = is_string($mode) ? trim($mode) : $mode;

            $token = $request->query('hub.verify_token')
                ?? $request->query('hub_verify_token')
                ?? $request->input('hub.verify_token')
                ?? $request->input('hub_verify_token');
            $token = is_string($token) ? trim($token) : $token;

            $challenge = $request->query('hub.challenge')
                ?? $request->query('hub_challenge')
                ?? $request->input('hub.challenge')
                ?? $request->input('hub_challenge');
            $challenge = is_string($challenge) ? trim($challenge) : $challenge;

            Log::channel('whatsapp')->info('Webhook verification attempt', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'connection_slug' => $connection->slug,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'mode' => $mode,
                'token_received' => $token ? (strlen($token) > 4 ? substr($token, 0, 4).'...' : '***') : null,
                'token_received_length' => $token ? strlen($token) : 0,
                'token_expected' => $connection->webhook_verify_token ? (strlen($connection->webhook_verify_token) > 4 ? substr($connection->webhook_verify_token, 0, 4).'...' : '***') : null,
                'token_expected_length' => $connection->webhook_verify_token ? strlen($connection->webhook_verify_token) : 0,
                'has_challenge' => ! empty($challenge),
                'challenge_length' => $challenge ? strlen($challenge) : 0,
            ]);

            // Check if connection has a verify token
            if (empty($connection->webhook_verify_token)) {
                Log::channel('whatsapp')->error('Webhook verification failed: connection has no verify token', [
                    'connection_id' => $connection->id,
                    'connection_slug' => $connection->slug,
                    'ip' => $request->ip()]);
                abort(403, 'Connection not configured for webhooks');
            }

            // Verify mode and token - Meta requires exact match
            $modeValid = $mode === 'subscribe';
            $expectedToken = $connection->webhook_verify_token;
            $expectedToken = is_string($expectedToken) ? trim($expectedToken) : $expectedToken;
            $tokenValid = ! empty($token) && ! empty($expectedToken) && hash_equals($expectedToken, $token);

            Log::channel('whatsapp')->info('Webhook verification checks', [
                'connection_id' => $connection->id,
                'mode_valid' => $modeValid,
                'token_valid' => $tokenValid,
                'has_challenge' => ! empty($challenge),
            ]);

            if ($modeValid && $tokenValid && ! empty($challenge)) {
                // Mark as subscribed
                $connection->update(['webhook_subscribed' => true]);

                Log::info('[Meta-WhatsApp-Webhook] GET verify success', ['connection_id' => $connection->id]);
                Log::channel('whatsapp')->info('Webhook verified successfully - returning challenge', [
                    'connection_id' => $connection->id,
                    'connection_slug' => $connection->slug,
                    'ip' => $request->ip(),
                    'challenge_length' => strlen($challenge),
                ]);

                // Meta expects the challenge string as plain text response (200 OK)
                // Must return exactly the challenge value, nothing else
                return response($challenge, 200)
                    ->header('Content-Type', 'text/plain; charset=UTF-8');
            }

            Log::channel('whatsapp')->warning('Webhook verification failed', [
                'connection_id' => $connection->id,
                'connection_slug' => $connection->slug,
                'ip' => $request->ip(),
                'mode' => $mode,
                'mode_valid' => $modeValid,
                'token_received' => $token ? (strlen($token) > 4 ? substr($token, 0, 4).'...' : '***') : null,
                'token_expected' => substr($connection->webhook_verify_token, 0, 4).'...',
                'token_valid' => $tokenValid,
                'has_challenge' => ! empty($challenge),
                'all_checks_passed' => $modeValid && $tokenValid && ! empty($challenge)]);

            abort(403, 'Forbidden');
        } catch (WebhookEventLockedException $e) {
            Log::channel('whatsapp')->warning('Webhook temporarily locked; asking Meta to retry', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Temporarily locked, retry later',
                'correlation_id' => $correlationId], 503);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('Webhook verification exception', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id ?? null,
                'connection_slug' => $connection->slug ?? null,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to let Laravel handle it, but log first
            throw $e;
        }
    }

    /**
     * Receive webhook endpoint (POST).
     * Connection is resolved by route model binding (slug or id).
     */
    public function receive(Request $request, WhatsAppConnection $connection)
    {
        $correlationId = $request->attributes->get('webhook_correlation_id', Str::uuid()->toString());

        if (! $this->signatureIsValid($request)) {
            Log::channel('whatsapp')->warning('Webhook signature verification failed', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'ip' => $request->ip(),
                'has_signature' => $request->headers->has('X-Hub-Signature-256'),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid webhook signature',
                'correlation_id' => $correlationId,
            ], 401);
        }

        $entryCount = is_array($request->input('entry')) ? count($request->input('entry')) : 0;
        Log::info('[Meta-WhatsApp-Webhook] POST receive hit', [
            'connection_id' => $connection->id,
            'connection_slug' => $connection->slug,
            'entry_count' => $entryCount,
            'payload_keys' => array_keys($request->all()),
        ]);
        Log::channel('whatsapp')->info('WebhookController::receive POST', [
            'correlation_id' => $correlationId,
            'connection_id' => $connection->id,
            'connection_slug' => $connection->slug,
            'payload_keys' => array_keys($request->all()),
            'entry_count' => $entryCount,
        ]);

        // Rate limit webhook reception per connection
        $key = 'webhook-receive-'.$connection->id;
        $maxAttempts = config('whatsapp.webhook.rate_limit', 100);
        $decayMinutes = config('whatsapp.webhook.rate_limit_decay', 1);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            Log::channel('whatsapp')->warning('Webhook rate limit exceeded', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'ip' => $request->ip()]);

            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded',
                'correlation_id' => $correlationId], 429);
        }
        RateLimiter::hit($key, $decayMinutes * 60);

        // Validate payload structure (basic check)
        $payload = $request->all();
        if (empty($payload) || ! isset($payload['entry'])) {
            Log::warning('[Meta-WhatsApp-Webhook] POST rejected: invalid payload (empty or no entry)', [
                'connection_id' => $connection->id,
                'payload_keys' => array_keys($payload),
            ]);
            Log::channel('whatsapp')->warning('Invalid webhook payload structure', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id]);

            return response()->json([
                'success' => false,
                'error' => 'Invalid payload',
                'correlation_id' => $correlationId], 400);
        }

        // Log message presence for debugging (Meta sends entry[].changes[].value.messages)
        $entries = $payload['entry'] ?? [];
        $firstChange = $entries[0]['changes'][0] ?? null;
        $firstValue = $firstChange['value'] ?? [];
        $messageCount = isset($firstValue['messages']) ? count($firstValue['messages']) : 0;
        Log::debug('[Meta-WhatsApp-Webhook] POST payload', [
            'connection_id' => $connection->id,
            'messages_count' => $messageCount,
            'field' => $firstChange['field'] ?? null,
        ]);
        Log::channel('whatsapp')->debug('Webhook payload messages check', [
            'correlation_id' => $correlationId,
            'connection_id' => $connection->id,
            'first_change_field' => $firstChange['field'] ?? null,
            'value_keys' => array_keys($firstValue),
            'messages_count' => $messageCount,
        ]);

        // Log payload size (for monitoring)
        $payloadSize = strlen(json_encode($payload));
        if ($payloadSize > 100000) { // 100KB
            Log::channel('whatsapp')->warning('Large webhook payload', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'size_bytes' => $payloadSize]);
        }

        try {
            // Process webhook with correlation ID. Meta can deliver duplicate
            // message/status events concurrently, so retry transient MySQL
            // deadlocks instead of dropping that webhook attempt.
            $attempt = 0;
            while (true) {
                try {
                    $attempt++;
                    $this->webhookProcessor->process($payload, $connection, $correlationId);
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    if (! $this->isTransientDatabaseConcurrencyError($e) || $attempt >= 5) {
                        throw $e;
                    }

                    usleep((100000 * $attempt) + random_int(10000, 75000));
                }
            }

            Log::info('[Meta-WhatsApp-Webhook] POST processed OK', ['connection_id' => $connection->id]);

            return response()->json([
                'success' => true,
                'correlation_id' => $correlationId], 200);
        } catch (WebhookEventLockedException $e) {
            // Transient contention: ask Meta to retry instead of dropping the event.
            Log::channel('whatsapp')->warning('Webhook deferred: connection busy, Meta will retry', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Temporarily locked, retry later',
                'correlation_id' => $correlationId], 503);
        } catch (\Throwable $e) {
            Log::warning('[Meta-WhatsApp-Webhook] POST processing failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            Log::channel('whatsapp')->error('Webhook processing error', [
                'correlation_id' => $correlationId,
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()]);

            app(\App\Services\AppNotificationService::class)->workspace(
                $connection->account_id,
                'whatsapp_webhook_processing_failed',
                'WhatsApp webhook failed',
                'A WhatsApp webhook could not be processed. Check System Health and replay the failed event if needed.',
                'error',
                route('app.whatsapp.connections.index'),
                [
                    'connection_id' => $connection->id,
                    'correlation_id' => $correlationId,
                    'error' => Str::limit($e->getMessage(), 300),
                    'dedupe_key' => 'connection_'.$connection->id,
                ]
            );

            // Still return 200 to Meta to prevent retries for processing errors
            // But log the error for investigation
            return response()->json([
                'success' => false,
                'error' => 'Processing failed',
                'correlation_id' => $correlationId], 200);
        }
    }

    protected function isTransientDatabaseConcurrencyError(\Illuminate\Database\QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Deadlock found')
            || str_contains($message, 'Serialization failure')
            || str_contains($message, 'Lock wait timeout exceeded');
    }

    /**
     * Resolve a connection by slug or ID.
     */
    protected function resolveConnection($value): ?WhatsAppConnection
    {
        if ($value instanceof WhatsAppConnection) {
            return $value;
        }

        $value = is_string($value) ? trim($value) : (string) $value;
        if ($value === '') {
            return null;
        }

        return WhatsAppConnection::where('slug', $value)
            ->orWhere('id', $value)
            ->first();
    }

    protected function resolveConnectionFromPayload(array $payload): ?WhatsAppConnection
    {
        $phoneNumberId = $this->extractPhoneNumberId($payload);
        if (! $phoneNumberId) {
            return null;
        }

        return WhatsAppConnection::where('phone_number_id', $phoneNumberId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, WhatsAppConnection>
     */
    protected function resolveConnectionsByWaba(array $payload): \Illuminate\Support\Collection
    {
        $wabaIds = collect($payload['entry'] ?? [])
            ->map(fn ($entry) => is_array($entry) && is_scalar($entry['id'] ?? null) ? trim((string) $entry['id']) : null)
            ->filter()
            ->unique()
            ->values();

        if ($wabaIds->isEmpty()) {
            return collect();
        }

        return WhatsAppConnection::whereIn('waba_id', $wabaIds->all())
            ->where('is_active', true)
            ->get();
    }

    protected function extractPhoneNumberId(array $payload): ?string
    {
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) (is_array($entry) ? ($entry['changes'] ?? []) : []) as $change) {
                $value = is_array($change) ? ($change['value'] ?? []) : [];
                $phoneNumberId = is_array($value) ? ($value['metadata']['phone_number_id'] ?? null) : null;
                if (is_string($phoneNumberId) && trim($phoneNumberId) !== '') {
                    return trim($phoneNumberId);
                }
            }
        }

        return null;
    }

    public static function centralVerifyToken(): string
    {
        $configured = PlatformSetting::get('whatsapp.central_webhook_verify_token')
            ?: config('whatsapp.webhook.verify_token');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return hash_hmac('sha256', 'ui-central-whatsapp-webhook', (string) config('app.key'));
    }

    protected function signatureIsValid(Request $request): bool
    {
        $verifier = app(WebhookSignatureVerifier::class);

        if (! $verifier->isStrict()) {
            return true;
        }

        if (! $verifier->hasAnySecret()) {
            return true;
        }

        return $verifier->isValid($request);
    }
}
