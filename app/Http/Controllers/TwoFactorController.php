<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorTotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function challenge(): Response
    {
        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request, TwoFactorTotpService $totp): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:12'],
        ]);

        $user = $request->user();
        abort_unless($user && $user->two_factor_secret, 403);

        if (! $totp->verify(decrypt($user->two_factor_secret), $validated['code'])) {
            return back()->withErrors(['code' => 'The verification code is invalid.']);
        }

        $request->session()->put('two_factor_passed_at', now()->timestamp);

        return redirect()->intended($user->isSuperAdmin() ? route('app.dashboard') : route('app.dashboard'));
    }

    public function prepare(Request $request, TwoFactorTotpService $totp): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => encrypt($totp->generateSecret()),
            'two_factor_enabled_at' => null,
        ])->save();

        return back()->with('success', 'Authenticator setup started.');
    }

    public function enable(Request $request, TwoFactorTotpService $totp): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:12'],
        ]);

        $user = $request->user();
        abort_unless($user->two_factor_secret, 422, 'Start 2FA setup before verifying a code.');

        if (! $totp->verify(decrypt($user->two_factor_secret), $validated['code'])) {
            return back()->withErrors(['code' => 'The verification code is invalid.']);
        }

        $user->forceFill(['two_factor_enabled_at' => now()])->save();
        $request->session()->put('two_factor_passed_at', now()->timestamp);

        return back()->with('success', 'Two-factor authentication enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
        ])->save();

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
