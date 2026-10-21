<?php

namespace App\Http\Middleware;

use App\Models\AccountApiRequestLog;
use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $durationMs = (microtime(true) - $start) * 1000;
        $duration = (int) round($durationMs);
        $logEnabled = (bool) PlatformSetting::get('analytics.log_api_requests', false);
        $logLevel = PlatformSetting::get('analytics.log_level', 'info');
        $account = $request->attributes->get('public_api_account') ?: $request->attributes->get('account');
        $apiKey = $request->attributes->get('public_api_key');

        if ($account) {
            AccountApiRequestLog::create([
                'account_id' => $account->id,
                'account_api_key_id' => $apiKey?->id,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $duration,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500) ?: null,
                'request_id' => $request->attributes->get(AddRequestCorrelationId::REQUEST_ID_ATTRIBUTE),
            ]);
        }

        if (! $logEnabled) {
            return $response;
        }

        Log::log($logLevel, 'API request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'account_id' => $account?->id,
        ]);

        return $response;
    }
}
