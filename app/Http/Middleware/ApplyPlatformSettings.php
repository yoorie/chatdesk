<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyPlatformSettings
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settingsService = app(PlatformSettingsService::class);

        // Apply general configuration
        $settingsService->applyGeneralConfig();

        // Apply localization settings
        $settingsService->applyLocalization();

        // Apply mail configuration
        $settingsService->applyMailConfig();

        // Apply Pusher configuration
        $settingsService->applyPusherConfig();

        // Apply WhatsApp Meta configuration
        $settingsService->applyWhatsAppConfig();

        // Apply Google OAuth configuration
        $settingsService->applyGoogleConfig();

        // Apply storage configuration
        $settingsService->applyStorageConfig();

        // Apply payment configuration
        $settingsService->applyPaymentConfig();

        // Apply performance configuration
        $settingsService->applyPerformanceConfig();

        // Apply logging configuration
        $settingsService->applyLoggingConfig();

        // Apply query logging configuration
        $settingsService->applyQueryLogging();

        // Apply AI configuration
        $settingsService->applyAIConfig();

        return $next($request);
    }
}
