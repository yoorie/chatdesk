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

        // Settings stay reachable so an admin can still sign out or fix the workspace.
        $route = $request->route()?->getName();
        if (($route && str_contains($route, 'settings')) || str_contains($request->path(), 'settings')) {
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
