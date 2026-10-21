<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google login is not configured yet.']);
        }

        $context = [];
        if ($request->query('intent') === 'signup') {
            $plan = $request->query('plan');
            $invite = $request->query('invite');

            if (is_string($plan) && $plan !== '' && $plan !== 'enterprise') {
                $context['plan_key'] = $plan;
            }
            if (is_string($invite) && $invite !== '') {
                $context['invite_token'] = $invite;
            }

            if ($context !== []) {
                $request->session()->put('google_signup_context', $context);
            }
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google login is not configured yet.']);
        }

	        try {
	            $googleUser = Socialite::driver('google')->user();
	        } catch (Throwable $e) {
	            Log::warning('Google login callback failed', [
	                'message' => $e->getMessage(),
	                'exception' => get_class($e),
	                'redirect_uri' => config('services.google.redirect'),
	                'has_code' => $request->filled('code'),
	                'has_state' => $request->filled('state'),
	                'error' => $request->query('error'),
	                'error_description' => $request->query('error_description'),
	            ]);

	            return redirect()->route('login')
	                ->withErrors(['email' => 'Google login failed. Please try again. Check that the authorized redirect URI is configured in Google Cloud.']);
	        }

        $email = $googleUser->getEmail();

        if (! $email) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google did not return an email address.']);
        }

        $user = User::query()
            ->where('google_id', $googleUser->getId())
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            if (! app(\App\Services\PlatformSettingsService::class)->isFeatureEnabled('user_registration')) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'New account registration is currently disabled.']);
            }

            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'email_verified_at' => now(),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'password' => Hash::make(Str::password(32)),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $user->google_id ?: $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar() ?: $user->avatar_url,
                'email_verified_at' => $user->email_verified_at ?: now(),
            ])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($contextRedirect = $this->applySignupContext($request, $user)) {
            return $contextRedirect;
        }

        return $this->redirectAfterLogin($request, $user);
    }

    private function isConfigured(): bool
    {
        return (bool) PlatformSetting::get('integrations.google_oauth_enabled', false)
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function redirectAfterLogin(Request $request, User $user): RedirectResponse
    {
        $intendedUrl = $request->session()->pull('url.intended');
        $intendedPath = $this->intendedPath($intendedUrl);

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

        $accounts = $user->accounts()->get()->merge($user->ownedAccounts()->get());

        if ($accounts->isEmpty()) {
            return redirect()->route('onboarding');
        }

        return redirect()->route('app.dashboard');
    }

    private function applySignupContext(Request $request, User $user): ?RedirectResponse
    {
        $context = $request->session()->pull('google_signup_context', []);
        if (! is_array($context) || $context === []) {
            return null;
        }

        $planKey = $context['plan_key'] ?? null;
        if (is_string($planKey) && $planKey !== '' && $planKey !== 'enterprise') {
            $planExists = \App\Models\Plan::query()
                ->where('key', $planKey)
                ->where('is_active', true)
                ->where('is_public', true)
                ->where('key', '!=', 'enterprise')
                ->exists();

            if ($planExists) {
                $request->session()->put('selected_plan_key', $planKey);
            }
        }

        $inviteToken = $context['invite_token'] ?? null;
        if (! is_string($inviteToken) || $inviteToken === '') {
            return null;
        }

        $invitation = \App\Models\AccountInvitation::where('token', $inviteToken)
            ->whereNull('accepted_at')
            ->first();

        if (! $invitation) {
            return redirect()->route('onboarding')
                ->with('error', 'This invitation is no longer available.');
        }

        if ($invitation->isExpired()) {
            return redirect()->route('onboarding')
                ->with('error', 'Your invitation has expired. Please ask the account owner to resend it.');
        }

        if (strcasecmp((string) $invitation->email, (string) $user->email) !== 0) {
            return redirect()->route('onboarding')
                ->with('error', 'This invitation belongs to a different email address.');
        }

        $account = $invitation->account;
        if ($account && ! $account->users()->where('user_id', $user->id)->exists()) {
            $account->users()->attach($user->id, [
                'role' => $invitation->role,
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        if ($account) {
            $request->session()->put('current_account_id', $account->id);
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
}
