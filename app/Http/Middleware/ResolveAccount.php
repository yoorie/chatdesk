<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Support\CurrentAccountResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveAccount
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
            return $next($request);
        }

        // Session value is only a preference: a missing or stale id falls back to any
        // workspace the user belongs to, so members are never bounced to onboarding.
        $sessionAccountId = session('current_account_id');
        $account = app(CurrentAccountResolver::class)->resolve($user, $sessionAccountId);

        if (! $account) {
            // Pointed at someone else's workspace and has none of their own: deny, as before.
            if ($sessionAccountId && Account::whereKey($sessionAccountId)->exists()) {
                abort(403, 'You do not have access to this account');
            }

            \Illuminate\Support\Facades\Auth::guard('web')->logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Your user is not part of this organisation yet. Ask an admin to invite you.',
            ]);
        }

        $request->attributes->set('account', $account);
        session(['current_account_id' => $account->id]);

        return $next($request);
    }
}
