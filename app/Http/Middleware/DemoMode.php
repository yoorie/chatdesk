<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;

/**
 * DEMO_MODE=true: block every write request (POST/PUT/PATCH/DELETE) except
 * login/logout and a few harmless endpoints, for everyone.
 */
class DemoMode
{
    public function handle(Request $request, Closure $next)
    {
        if (! Demo::enabled() || $request->isMethodSafe()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        foreach ((array) config('demo.allowed_writes', []) as $pattern) {
            if (fnmatch($pattern, $path)) {
                return $next($request);
            }
        }

        $message = (string) config('demo.message', 'Disabled in demo');

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message, 'demo' => true], 403);
        }

        return back(303)->with('error', $message);
    }
}
