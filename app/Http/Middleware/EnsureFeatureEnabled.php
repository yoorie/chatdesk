<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $settingsService = app(\App\Services\PlatformSettingsService::class);

        if (! $settingsService->isFeatureEnabled($feature)) {
            abort(403, 'This feature is currently disabled.');
        }

        return $next($request);
    }
}
