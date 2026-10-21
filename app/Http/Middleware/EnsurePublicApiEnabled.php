<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePublicApiEnabled
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
        $publicApiEnabled = (bool) ($features['public_api'] ?? false);
        $integrationsEnabled = (bool) \App\Models\PlatformSetting::get('integrations.api_enabled', false);

        if (! $publicApiEnabled || ! $integrationsEnabled) {
            abort(403, 'Public API access is currently disabled.');
        }

        return $next($request);
    }
}
