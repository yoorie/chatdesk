<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $account = $request->attributes->get('account') ?? current_account();

        if (! $account) {
            abort(404, 'Account not found');
        }

        if (! module_enabled($account, $moduleKey)) {
            abort(403, 'Module is not enabled for this account');
        }

        return $next($request);
    }
}
