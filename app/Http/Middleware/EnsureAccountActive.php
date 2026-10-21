<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! $account) {
            abort(404, 'Account not found.');
        }

        // Super admins can access disabled accounts
        $user = $request->user();
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        // Always allow access to billing routes (needed for payment processing)
        $route = $request->route()?->getName();
        $path = $request->path();
        if (($route && (str_contains($route, 'billing') || str_contains($route, 'settings')))
            || str_contains($path, 'billing') || str_contains($path, 'settings')) {
            return $next($request);
        }

        // Check if account is active
        if (! $account->isActive()) {
            return inertia('AccountSuspended', [
                'account' => [
                    'name' => $account->name,
                    'status' => $account->status,
                    'disabled_reason' => $account->disabled_reason]])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
