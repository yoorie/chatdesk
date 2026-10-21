<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\MobileAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $provided = $request->bearerToken();
        if (! is_string($provided) || $provided === '') {
            return response()->json(['message' => 'Mobile token is required.'], 401);
        }

        $token = MobileAccessToken::query()
            ->where('token_prefix', substr($provided, 0, 24))
            ->with(['user', 'account'])
            ->first();

        if (! $token || ! hash_equals($token->token_hash, hash('sha256', $provided)) || ! $token->isUsable()) {
            return response()->json(['message' => 'Invalid or expired mobile token.'], 401);
        }

        $user = $token->user;
        if (! $user) {
            return response()->json(['message' => 'Invalid mobile token user.'], 401);
        }

        $account = $this->resolveAccount($request, $token);
        if ($account && ! $user->canAccessAccount($account)) {
            return response()->json(['message' => 'You do not have access to this workspace.'], 403);
        }

        $token->forceFill([
            'account_id' => $account?->id,
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ])->save();

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('mobile_access_token', $token);
        if ($account) {
            $request->attributes->set('account', $account);
        }

        return $next($request);
    }

    private function resolveAccount(Request $request, MobileAccessToken $token): ?Account
    {
        $requestedAccountId = $request->header('X-App-Account') ?: $request->input('account_id');
        if ($requestedAccountId) {
            return Account::find((int) $requestedAccountId);
        }

        if ($token->account) {
            return $token->account;
        }

        $user = $token->user;

        return $user?->ownedAccounts()->first() ?: $user?->accounts()->first();
    }
}
