<?php

namespace App\Modules\WhatsApp\Http\Middleware;

use App\Modules\WhatsApp\Services\WebhookSignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

class WebhookSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $verifier = app(WebhookSignatureVerifier::class);

        if ($request->isMethod('post') && $verifier->hasAnySecret()) {
            $strictSignature = $verifier->isStrict();
            $hasSignature = $request->headers->has('X-Hub-Signature-256');

            if (! $hasSignature) {
                $message = $strictSignature
                    ? 'Webhook POST rejected: missing X-Hub-Signature-256 (app_secret is set)'
                    : 'Webhook POST accepted without X-Hub-Signature-256 because strict signature verification is disabled';

                Log::warning('[Meta-WhatsApp-Webhook] '.$message);
                Log::channel('whatsapp')->warning($message);

                if ($strictSignature) {
                    return response('Missing signature', 401);
                }

                $this->notifySignatureIssue('missing_signature', $message);
            }

            if ($hasSignature && ! $verifier->isValid($request)) {
                $message = $strictSignature
                    ? 'Webhook POST rejected: invalid signature (check META_APP_SECRET matches Meta App Secret)'
                    : 'Webhook POST accepted with invalid signature because strict signature verification is disabled';

                $context = $this->signatureFailureContext($request, $verifier);
                Log::warning('[Meta-WhatsApp-Webhook] '.$message, $context);
                Log::channel('whatsapp')->warning($message, $context);

                if ($strictSignature) {
                    return response('Invalid signature', 401);
                }

                $this->notifySignatureIssue('invalid_signature', $message);
            }
        }

        // Check IP allowlist if configured
        $allowedIpsConfig = config('whatsapp.webhook.allowed_ips', '');
        if (! empty($allowedIpsConfig)) {
            $allowedIps = array_map('trim', explode(',', $allowedIpsConfig));
            $requestIp = $request->ip();

            // Check exact match or CIDR notation (IPv4)
            $allowed = false;
            foreach ($allowedIps as $allowedIp) {
                if ($requestIp === $allowedIp) {
                    $allowed = true;
                    break;
                }
                if (str_contains($allowedIp, '/')) {
                    $parts = explode('/', $allowedIp, 2);
                    if (count($parts) === 2) {
                        $network = trim($parts[0]);
                        $prefix = (int) $parts[1];
                        if ($prefix >= 0 && $prefix <= 32 && $this->ipInCidr($requestIp, $network, $prefix)) {
                            $allowed = true;
                            break;
                        }
                    }
                }
            }

            if (! $allowed) {
                Log::channel('whatsapp')->warning('Webhook blocked: IP not in allowlist', [
                    'ip' => $requestIp,
                    'connection_id' => $request->route('connection')?->id,
                    'allowed_ips' => $allowedIps]);

                abort(403, 'Forbidden');
            }
        }

        // Generate correlation ID for request tracking
        $correlationId = uniqid('wh_', true);
        $request->attributes->set('webhook_correlation_id', $correlationId);

        // Log request (truncated payload) - log early to catch all requests
        $connectionParam = $request->route('connection');
        $connection = $connectionParam instanceof \App\Modules\WhatsApp\Models\WhatsAppConnection
            ? $connectionParam
            : \App\Modules\WhatsApp\Models\WhatsAppConnection::where('slug', (string) $connectionParam)
                ->orWhere('id', (string) $connectionParam)
                ->first();
        Log::channel('whatsapp')->info('Webhook request received in WebhookSecurity', [
            'correlation_id' => $correlationId,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'connection_id' => $connection?->id,
            'connection_slug' => $connection?->slug,
            'connection_param' => $request->route()->parameter('connection'),
            'has_entry' => $request->has('entry'),
            'query_params' => $request->query(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 100), // Truncate
        ]);

        try {
            $response = $next($request);

            Log::channel('whatsapp')->info('Webhook request processed', [
                'correlation_id' => $correlationId,
                'status' => $response->getStatusCode()]);

            return $response;
        } catch (HttpExceptionInterface $e) {
            Log::channel('whatsapp')->warning('Webhook request rejected', [
                'correlation_id' => $correlationId,
                'status' => $e->getStatusCode(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            // Log error safely (no stack trace in response)
            Log::channel('whatsapp')->error('Webhook processing exception', [
                'correlation_id' => $correlationId,
                'error' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500), // Truncated trace
            ]);

            // Return generic error (no stack trace)
            return response()->json([
                'success' => false,
                'error' => 'Webhook processing failed',
                'correlation_id' => $correlationId], 500);
        }
    }

    /**
     * Check if an IPv4 address is within a CIDR range.
     */
    private function ipInCidr(string $ip, string $network, int $prefixLen): bool
    {
        $ipLong = @ip2long($ip);
        $networkLong = @ip2long($network);
        if ($ipLong === false || $networkLong === false) {
            return false;
        }
        // 32-bit safe: use unsigned
        $ipLong = $ipLong & 0xFFFFFFFF;
        $networkLong = $networkLong & 0xFFFFFFFF;
        $mask = $prefixLen === 0 ? 0 : (0xFFFFFFFF << (32 - $prefixLen)) & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($networkLong & $mask);
    }

    protected function notifySignatureIssue(string $issue, string $message): void
    {
        try {
            app(\App\Services\AppNotificationService::class)->platform(
                'webhook_signature_not_strict',
                'WhatsApp webhook signature not enforced',
                $message,
                'warning',
                route('platform.settings', ['tab' => 'integrations']),
                ['issue' => $issue, 'dedupe_key' => 'whatsapp_webhook_signature_'.$issue]
            );
        } catch (\Throwable) {
            // Never block webhook delivery because notification storage failed.
        }
    }

    /**
     * Safe diagnostics for signature mismatches (no secret values).
     * Helps distinguish wrong Meta app secret vs multi-app webhook delivery.
     *
     * @return array<string, mixed>
     */
    protected function signatureFailureContext(Request $request, WebhookSignatureVerifier $verifier): array
    {
        $body = $request->getContent();
        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $secrets = $verifier->secrets($verifier->accountIdForRequest($request));

        $context = [
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 80),
            'content_length_header' => $request->header('Content-Length'),
            'body_len' => strlen($body),
            'signature_prefix' => str_starts_with($signature, 'sha256=')
                ? 'sha256='.substr($signature, 7, 8)
                : substr($signature, 0, 16),
            'secret_count' => count($secrets),
            'secret_sha16' => array_map(
                static fn (string $secret): string => substr(hash('sha256', $secret), 0, 16),
                $secrets
            ),
        ];

        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $entry = $decoded['entry'][0] ?? null;
            $change = is_array($entry) ? ($entry['changes'][0] ?? null) : null;
            $value = is_array($change) ? ($change['value'] ?? null) : null;

            $context['object'] = $decoded['object'] ?? null;
            $context['waba_id'] = is_array($entry) ? ($entry['id'] ?? null) : null;
            $context['phone_number_id'] = is_array($value) ? ($value['metadata']['phone_number_id'] ?? null) : null;
            $context['field'] = is_array($change) ? ($change['field'] ?? null) : null;
        }

        return $context;
    }
}
