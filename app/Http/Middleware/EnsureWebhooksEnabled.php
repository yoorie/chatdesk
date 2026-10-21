<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebhooksEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settingsService = app(\App\Services\PlatformSettingsService::class);
        $features = $settingsService->getFeatures();
        $webhooksEnabled = (bool) ($features['webhooks'] ?? true);

        // Check if webhooks are explicitly disabled in integrations settings
        // Default to true (enabled) if not set
        $integrationsWebhooksEnabled = \App\Models\PlatformSetting::get('integrations.webhooks_enabled');
        $integrationsEnabled = $integrationsWebhooksEnabled === null ? true : (bool) $integrationsWebhooksEnabled;

        $razorpayEnabled = filter_var(
            \App\Models\PlatformSetting::get('payment.razorpay_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );

        // Log webhook request for debugging
        \Log::channel('whatsapp')->info('Webhook request received in EnsureWebhooksEnabled', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'webhooks_enabled' => $webhooksEnabled,
            'integrations_enabled' => $integrationsEnabled,
            'integrations_webhooks_enabled_raw' => $integrationsWebhooksEnabled,
            'connection_param' => $request->route('connection'),
        ]);

        // Only block if explicitly disabled (both must be true to allow)
        if (! $webhooksEnabled || ($integrationsWebhooksEnabled !== null && ! $integrationsEnabled)) {
            \Log::warning('[Meta-WhatsApp-Webhook] Request blocked: webhooks disabled', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            \Log::channel('whatsapp')->warning('Webhook blocked: webhooks disabled', [
                'path' => $request->path(),
                'webhooks_enabled' => $webhooksEnabled,
                'integrations_enabled' => $integrationsEnabled,
                'integrations_webhooks_enabled_raw' => $integrationsWebhooksEnabled,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Webhooks are currently disabled.',
            ], 503);
        }

        if ($request->is('webhooks/razorpay') && ! $razorpayEnabled) {
            return response()->json([
                'success' => false,
                'message' => 'Razorpay webhooks are currently disabled.',
            ], 503);
        }

        return $next($request);
    }
}
