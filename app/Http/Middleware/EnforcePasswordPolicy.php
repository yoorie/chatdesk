<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route()?->getName();

        if ($request->session()->has('impersonator_id')) {
            return $next($request);
        }

        if ($user?->force_password_reset_at && ! in_array($route, [
            'password.update',
            'logout',
            'profile.edit',
            'profile.update',
            'app.settings',
        ], true)) {
            $target = $user->isSuperAdmin()
                ? route('profile.edit')
                : route('app.settings', ['tab' => 'profile']);

            return redirect($target)->with('warning', 'Update your password before continuing.');
        }

        return $next($request);
    }
}
