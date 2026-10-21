<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hide the routes of a module that is switched off for the organisation.
 */
class EnsureModuleEntitled
{
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $account = $request->attributes->get('account') ?? current_account();
        abort_if($account && ! module_enabled($account, $moduleKey), 404);

        return $next($request);
    }
}
