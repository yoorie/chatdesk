<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): RedirectResponse
    {
        return redirect()->route('app.settings', ['tab' => 'profile'])
            ->with('status', session('status'));
    }

    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        if (config('session.driver') === 'database' && \Illuminate\Support\Facades\Schema::hasTable(config('session.table', 'sessions'))) {
            \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        $request->user()->forceFill(['sessions_revoked_at' => now()])->save();

        return back()->with('success', 'Other sessions revoked.');
    }

    private function sessionPayload(Request $request): array
    {
        if (config('session.driver') !== 'database' || ! \Illuminate\Support\Facades\Schema::hasTable(config('session.table', 'sessions'))) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'is_current' => hash_equals((string) $session->id, (string) $request->session()->getId()),
            ])
            ->all();
    }

    private function twoFactorSetupPayload(?\App\Models\User $user): ?array
    {
        if (! $user?->two_factor_secret || $user->two_factor_enabled_at) {
            return null;
        }

        $secret = decrypt($user->two_factor_secret);
        $issuer = config('app.name', 'ChatDesk');

        return [
            'secret' => $secret,
            'otpauth_url' => app(\App\Services\TwoFactorTotpService::class)->otpauthUrl($issuer, $user->email, $secret),
        ];
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $countryCode = $validated['country_code'] ?? '+91';
        $localPhone = preg_replace('/\D+/', '', (string) ($validated['phone'] ?? ''));
        $validated['country_code'] = $countryCode;
        $validated['phone'] = $localPhone !== '' ? $countryCode.$localPhone : null;
        $validated['timezone'] = $this->timezoneForCountryCode($countryCode);

        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        // Check if profile is now complete
        $user = $request->user();
        $isProfileComplete = ! empty($user->name) &&
                             ! empty($user->email);

        if ($isProfileComplete) {
            // If profile is complete and user came from onboarding, redirect to dashboard
            if (session('redirect_after_profile_complete')) {
                session()->forget('redirect_after_profile_complete');

                return Redirect::route('app.dashboard')->with('success', 'Profile updated successfully.');
            }
        }

        return Redirect::back()->with('success', 'Profile updated successfully.');
    }

    protected function timezoneForCountryCode(string $countryCode): string
    {
        return [
            '+91' => 'Asia/Kolkata',
            '+1' => 'America/New_York',
            '+44' => 'Europe/London',
            '+971' => 'Asia/Dubai',
            '+65' => 'Asia/Singapore',
            '+61' => 'Australia/Sydney',
            '+49' => 'Europe/Berlin',
            '+33' => 'Europe/Paris',
            '+81' => 'Asia/Tokyo',
            '+880' => 'Asia/Dhaka',
            '+94' => 'Asia/Colombo',
            '+977' => 'Asia/Kathmandu',
        ][$countryCode] ?? 'Asia/Kolkata';
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password']]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
