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
            'status' => session('status'),
            'canRegister' => ! \App\Models\User::query()->exists(),
        ]);
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

        if ($intendedUrl) {
            return redirect($intendedUrl);
        }

        // Default redirect logic for regular users
        // Check if user has accounts
        $accounts = $user->accounts()->get()->merge($user->ownedAccounts()->get());

        if ($accounts->isEmpty()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();

            return redirect()->route('login')->withErrors([
                'email' => 'Your user is not part of this organisation yet. Ask an admin to invite you.',
            ]);
        }

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
