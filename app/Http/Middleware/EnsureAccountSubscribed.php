<?php

namespace App\Http\Middleware;

use App\Core\Billing\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountSubscribed
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Open-source edition: no plans or billing, every action is allowed.
        return $next($request);
    }

    protected function blockedActionResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'billing_required' => true,
            ], 402);
        }

        return redirect()->route('app.billing.index', ['tab' => 'plans'])
            ->withErrors(['billing' => $message])
            ->with('error', $message);
    }
}
