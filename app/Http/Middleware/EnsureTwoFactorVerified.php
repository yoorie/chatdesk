<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($request->session()->has('impersonator_id')) {
            return $next($request);
        }

        $route = $request->route()?->getName();
        if ($route && (
            str_starts_with($route, 'two-factor.')
            || in_array($route, ['logout', 'password.update', 'profile.edit', 'profile.update', 'app.settings', 'app.settings.notifications'], true)
        )) {
            return $next($request);
        }

        $requiresTwoFactor = $user->two_factor_enabled_at !== null;

        if (! $requiresTwoFactor && PlatformSetting::get('security.require_2fa', false)) {
            $account = $request->attributes->get('account') ?? current_account();
            $requiresTwoFactor = $user->isSuperAdmin()
                || ($account && (int) $account->owner_id === (int) $user->id);
        }

        if (! $requiresTwoFactor) {
            return $next($request);
        }

        if (! $user->two_factor_enabled_at) {
            $target = $user->isSuperAdmin()
                ? route('profile.edit')
                : route('app.settings', ['tab' => 'profile']);

            return redirect($target)->with('warning', 'Two-factor authentication is required for this account.');
        }

        if (! $request->session()->has('two_factor_passed_at')) {
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
