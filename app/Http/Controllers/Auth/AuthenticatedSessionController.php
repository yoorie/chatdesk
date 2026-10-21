<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'googleOAuthEnabled' => (bool) \App\Models\PlatformSetting::get('integrations.google_oauth_enabled', false)
                && filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect')),
            'status' => session('status')]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        $intendedUrl = $request->session()->pull('url.intended');
        $intendedPath = $this->intendedPath($intendedUrl);

        // Super admins should go directly to platform dashboard
        if ($user->isSuperAdmin()) {
            if ($intendedUrl && $intendedPath && str_starts_with($intendedPath, '/platform')) {
                return redirect($intendedUrl);
            }

            return redirect()->route('app.dashboard');
        }

        if ($intendedPath && str_starts_with($intendedPath, '/platform')) {
            $intendedUrl = null;
        }

        if ($intendedUrl) {
            return redirect($intendedUrl);
        }

        // Default redirect logic for regular users
        // Check if user has accounts
        $accounts = $user->accounts()->get()->merge($user->ownedAccounts()->get());

        if ($accounts->isEmpty()) {
            // Redirect to onboarding
            return redirect()->route('onboarding');
        }

        // Redirect to first account dashboard
        $firstAccount = $accounts->first();

        return redirect()->route('app.dashboard');
    }

    private function intendedPath(?string $intendedUrl): ?string
    {
        if (! $intendedUrl) {
            return null;
        }

        $path = parse_url($intendedUrl, PHP_URL_PATH);

        return is_string($path) ? '/'.ltrim($path, '/') : null;
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
