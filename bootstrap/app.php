<?php

use App\Support\ReleasePackageGuard;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// A copied dev cache (packages.php / services.php) names Laravel Pail and
// other packages that are not in the production vendor. Drop it before the
// application reads it, including the browser installer.
ReleasePackageGuard::discardUnreadableProviders(__DIR__.'/cache');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Broadcasting channels are loaded manually in routes/web.php
    // ->withBroadcasting(__DIR__.'/../routes/channels.php', [
    //     'middleware' => ['web', 'auth'],
    // ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\DemoMode::class,
            \App\Http\Middleware\ApplyPlatformSettings::class,
            \App\Http\Middleware\EnforceRateLimits::class,
            \App\Http\Middleware\EnforcePasswordPolicy::class,
            \App\Http\Middleware\EnsureTwoFactorVerified::class,
            \App\Http\Middleware\EnsureMaintenanceMode::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Request correlation ID first (so all logs can include it)
        $middleware->web(prepend: [
            \App\Http\Middleware\AddRequestCorrelationId::class,
        ]);

        // Exclude broadcasting/auth and webhooks from CSRF verification
        // Broadcasting uses its own authentication mechanism
        // Webhooks are verified via signature/token
        // Demo mode also guards API/webhook writes.
        $middleware->api(prepend: [\App\Http\Middleware\DemoMode::class]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
            'broadcasting/auth',
            'webhooks/*',
        ]);

        $middleware->alias([
            'account.resolve' => \App\Http\Middleware\ResolveAccount::class,
            'module.enabled' => \App\Http\Middleware\EnsureModuleEnabled::class,
            'super.admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'account.active' => \App\Http\Middleware\EnsureAccountActive::class,
            'account.subscribed' => \App\Http\Middleware\EnsureAccountSubscribed::class,
            'module.entitled' => \App\Http\Middleware\EnsureModuleEntitled::class,
            'rate.limit' => \App\Http\Middleware\EnforceRateLimits::class,
            'profile.complete' => \App\Http\Middleware\EnsureProfileComplete::class,
            'feature.enabled' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'webhooks.enabled' => \App\Http\Middleware\EnsureWebhooksEnabled::class,
            'public-api.enabled' => \App\Http\Middleware\EnsurePublicApiEnabled::class,
            'public-api.auth' => \App\Http\Middleware\AuthenticatePublicApiKey::class,
            'mobile.auth' => \App\Http\Middleware\AuthenticateMobileToken::class,
            'log.api' => \App\Http\Middleware\LogApiRequests::class,
            'restrict.chat.agent' => \App\Http\Middleware\RestrictChatAgentAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle unauthenticated exceptions for Inertia requests
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            // Check if it's an authentication exception and an Inertia request
            if ($e instanceof \Illuminate\Auth\AuthenticationException) {
                if ($request->header('X-Inertia')) {
                    return \Inertia\Inertia::location('/login');
                }

                // For non-Inertia requests, let Laravel handle it (will return JSON or redirect)
                return null;
            }

            // Validation failures are expected form responses. Let Laravel/Inertia
            // redirect back with the error bag instead of converting them to 500s.
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return null;
            }

            // Return Inertia 404 page for Inertia requests so the client doesn't crash (e.g. "Cannot read properties of null (reading 'component')")
            $is404 = $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                || ($e instanceof \Illuminate\Http\Exceptions\HttpException && $e->getStatusCode() === 404)
                || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
                || (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 404);
            if ($request->header('X-Inertia') && $is404) {
                return \Inertia\Inertia::render('Error/NotFound')->toResponse($request)->setStatusCode(404);
            }

            // Plan-gated feature: explain which plan includes it and link to billing.
            if ($e instanceof \App\Exceptions\FeatureNotOnPlanException && ! $request->expectsJson()) {
                return \Inertia\Inertia::render('Error/Forbidden', [
                    'reason' => 'plan',
                    'feature' => $e->featureLabel(),
                    'requiredPlan' => $e->requiredPlan,
                    'billingUrl' => \Illuminate\Support\Facades\Route::has('app.billing.index')
                        ? route('app.billing.index')
                        : '/app/settings/billing',
                ])->toResponse($request)->setStatusCode(403);
            }

            // Return Inertia 403 page for Inertia requests
            $is403 = ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 403)
                || (method_exists($e, 'getStatusCode') && $e->getStatusCode() === 403);
            if ($request->header('X-Inertia') && $is403) {
                return \Inertia\Inertia::render('Error/Forbidden')->toResponse($request)->setStatusCode(403);
            }

            // Keep recoverable HTTP errors in the same Inertia error family.
            $httpStatus = $e instanceof \Illuminate\Session\TokenMismatchException
                ? 419
                : (method_exists($e, 'getStatusCode') ? $e->getStatusCode() : null);
            $errorPage = [419 => 'Error/SessionExpired', 429 => 'Error/RateLimited', 503 => 'Error/Maintenance'][$httpStatus] ?? null;
            if ($request->header('X-Inertia') && $errorPage) {
                return \Inertia\Inertia::render($errorPage)->toResponse($request)->setStatusCode($httpStatus);
            }

            // Return Inertia 500 page for Inertia requests so users see a friendly message
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $is500 = $statusCode >= 500 || ! method_exists($e, 'getStatusCode');
            if ($request->header('X-Inertia') && $is500) {
                $context = [
                    'path' => $request->path(),
                    'method' => $request->method(),
                ];
                if ($request->user()) {
                    $context['user_id'] = $request->user()->id;
                }
                if ($request->attributes->get('account')) {
                    $context['account_id'] = $request->attributes->get('account')->id;
                }
                if ($request->attributes->has('webhook_correlation_id')) {
                    $context['correlation_id'] = $request->attributes->get('webhook_correlation_id');
                }
                if ($request->attributes->has(\App\Http\Middleware\AddRequestCorrelationId::REQUEST_ID_ATTRIBUTE)) {
                    $context['request_id'] = $request->attributes->get(\App\Http\Middleware\AddRequestCorrelationId::REQUEST_ID_ATTRIBUTE);
                }
                \Illuminate\Support\Facades\Log::error('Server error (Inertia)', array_merge($context, [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]));

                return \Inertia\Inertia::render('Error/ServerError')->toResponse($request)->setStatusCode(500);
            }

            // Don't leak stack traces in production
            if (app()->environment('production')) {
                // For webhook routes, always return generic errors
                if ($request->is('webhooks/*')) {
                    $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                        ? $e->getStatusCode()
                        : (isset($e->status) ? (int) $e->status : 500);

                    return new \Symfony\Component\HttpFoundation\JsonResponse([
                        'success' => false,
                        'error' => $statusCode >= 500
                            ? 'An error occurred processing the webhook'
                            : \Symfony\Component\HttpFoundation\Response::$statusTexts[$statusCode] ?? 'Webhook request rejected',
                    ], $statusCode);
                }

                // For API routes, return JSON errors without stack traces
                // Only handle if it's not already handled by Laravel's default handler
                if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                    $statusCode = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
                        ? $e->getStatusCode()
                        : (isset($e->status) ? (int) $e->status : 500);

                    $safeMessage = $statusCode >= 500
                        ? 'Server error'
                        : ($e->getMessage() ?: 'Request rejected');

                    return new \Symfony\Component\HttpFoundation\JsonResponse([
                        'message' => $safeMessage,
                        'error' => 'Server error',
                    ], $statusCode);
                }
            }

            return null; // Let Laravel handle other exceptions
        });
    })->create();
