<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnforceRateLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip rate limiting for health checks, static assets, and webhooks
        if ($request->is('up')
            || $request->is('health')
            || $request->is('webhooks/*')
            || $request->is('*.css')
            || $request->is('*.js')
            || $request->is('*.jpg')
            || $request->is('*.png')
            || $request->is('*.gif')
            || $request->is('*.ico')) {
            return $next($request);
        }

        $settingsService = app(PlatformSettingsService::class);
        $security = $settingsService->getSecurity();

        // Apply IP whitelist if configured (skip for webhooks - they have their own security)
        if (! empty($security['ip_whitelist']) && ! $request->is('webhooks/*')) {
            $allowedIps = array_map('trim', explode(',', $security['ip_whitelist']));
            $clientIp = $request->ip();

            $isAllowed = false;
            foreach ($allowedIps as $allowedIp) {
                if ($this->ipMatches($clientIp, $allowedIp)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (! $isAllowed) {
                abort(403, 'Access denied from this IP address');
            }
        }

        // Apply web rate limit
        if ($request->is('api/*')) {
            $rateLimit = $security['api_rate_limit'] ?? 60;
        } else {
            $rateLimit = $security['web_rate_limit'] ?? 120;
        }

        $key = 'rate-limit:'.$request->ip().':'.$request->path();

        if (RateLimiter::tooManyAttempts($key, $rateLimit)) {
            $seconds = RateLimiter::availableIn($key);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Too many requests. Please try again in '.$seconds.' seconds.'], 429);
            }

            abort(429, 'Too many requests. Please try again in '.$seconds.' seconds.');
        }

        RateLimiter::hit($key, 60); // 1 minute window

        return $next($request);
    }

    /**
     * Check if an IP matches a pattern (supports CIDR notation).
     */
    private function ipMatches(string $ip, string $pattern): bool
    {
        if (strpos($pattern, '/') !== false) {
            // CIDR notation
            [$subnet, $mask] = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return $ip === $pattern;
    }
}
