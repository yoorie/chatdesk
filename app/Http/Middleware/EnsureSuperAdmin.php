<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            // Store intended URL for redirect after login
            return redirect()->guest(route('login'));
        }

        if (! $user->isSuperAdmin()) {
            $impersonatorId = $request->session()->get('impersonator_id');
            $impersonator = $impersonatorId ? User::find($impersonatorId) : null;

            if ($impersonator?->isSuperAdmin()) {
                Auth::loginUsingId($impersonator->id);
                $request->session()->forget([
                    'impersonator_id',
                    'impersonated_account_id',
                    'impersonated_user_id',
                    'current_account_id',
                ]);

                return redirect($request->fullUrl())
                    ->with('info', 'Impersonation ended. You are back in the admin panel.');
            }

            abort(403, 'Access denied. Super admin privileges required.');
        }

        return $next($request);
    }
}
