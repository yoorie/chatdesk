<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountInvitation;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ChatDesk open-source edition runs one organisation.
 * - The very first person to register becomes the admin and the organisation is created.
 * - After that, people can only join through a team invite sent by an admin.
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $invite = $this->pendingInvite($request->query('invite'));

        if (User::query()->exists() && ! $invite) {
            return redirect()->route('login')->with('error', 'Registration is invite-only. Ask your ChatDesk admin to invite you.');
        }

        return Inertia::render('Auth/Register', [
            'invite' => $invite ? [
                'token' => $invite->token,
                'email' => $invite->email,
                'account_name' => $invite->account?->name,
                'role' => $invite->role,
            ] : null,
        ]);
    }

    public function store(\App\Http\Requests\RegisterRequest $request, AccountProvisioner $provisioner): RedirectResponse
    {
        $validated = $request->validated();
        $isFirstUser = ! User::query()->exists();
        $invitation = $this->pendingInvite($validated['invite_token'] ?? null);

        abort_unless($isFirstUser || $invitation, 403, 'Registration is invite-only. Ask your ChatDesk admin to invite you.');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password'])]);

        if ($isFirstUser) {
            $user->forceFill(['email_verified_at' => now()])->save();
            $account = $provisioner->create($user, config('app.name', 'ChatDesk'), ['workspace_type' => 'business']);
        } else {
            $account = $invitation->account;
            if ($account && ! $account->users()->where('user_id', $user->id)->exists()) {
                $account->users()->attach($user->id, ['role' => $invitation->role]);
            }
            $invitation->update(['accepted_at' => now()]);
        }

        event(new Registered($user));
        Auth::login($user);
        session(['current_account_id' => $account->id]);

        return redirect()->route('app.dashboard');
    }

    private function pendingInvite(?string $token): ?AccountInvitation
    {
        if (! $token) {
            return null;
        }

        $invitation = AccountInvitation::where('token', $token)->whereNull('accepted_at')->first();

        return $invitation && ! $invitation->isExpired() ? $invitation : null;
    }
}
