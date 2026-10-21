<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip maintenance mode check for webhooks (they need to work even during maintenance)
        if ($request->is('webhooks/*')) {
            return $next($request);
        }

        if ($this->shouldBypassMaintenance($request)) {
            return $next($request);
        }

        $settingsService = app(PlatformSettingsService::class);

        if ($settingsService->isMaintenanceMode()) {
            if ($request->user()?->isSuperAdmin()) {
                return $next($request);
            }

            $message = $settingsService->getGeneral()['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back shortly.';

            return $this->maintenanceResponse($request, $message);
        }

        return $next($request);
    }

    private function maintenanceResponse(Request $request, string $message): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::render('Error/Maintenance', [
                'message' => $message,
            ])->toResponse($request)->setStatusCode(503);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'maintenance' => true,
            ], 503);
        }

        return response()->view('maintenance', [
            'message' => $message,
        ], 503);
    }


    private function shouldBypassMaintenance(Request $request): bool
    {
        // Maintenance middleware runs before the auth middleware in this app's web stack.
        // Let auth and platform-admin routes continue so Laravel can load the session,
        // authenticate the user, and then enforce the existing super.admin middleware.
        return $request->is('login')
            || $request->is('logout')
            || $request->is('register')
            || $request->is('forgot-password')
            || $request->is('reset-password*')
            || $request->is('verify-email')
            || $request->is('verify-email/*')
            || $request->is('email/verify*')
            || $request->is('email/verification-notification')
            || $request->is('two-factor-challenge')
            || $request->is('cron/run')
            || $request->is('api/voice-bridge/*')
            || $request->is('platform')
            || $request->is('platform/*')
            || $request->is('broadcasting/auth');
    }
}
