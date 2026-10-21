<?php

namespace App\Http\Middleware;

use App\Models\AccountApiKey;
use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePublicApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) PlatformSetting::get('integrations.api_key', '');
        $provided = $request->bearerToken()
            ?: $request->headers->get('X-API-Key')
            ?: $request->headers->get('x-api-key');

        if (! is_string($provided) || $provided === '') {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if ($configuredKey === '' || ! hash_equals($configuredKey, $provided)) {
            $key = AccountApiKey::query()
                ->whereNull('revoked_at')
                ->where('token_prefix', substr($provided, 0, 18))
                ->with('account')
                ->first();

            if (! $key || ! hash_equals($key->token_hash, hash('sha256', $provided))) {
                return response()->json(['message' => 'Invalid API key.'], 401);
            }

            $key->forceFill([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ])->save();

            $request->attributes->set('public_api_key', $key);
            $request->attributes->set('public_api_account', $key->account);
            $request->attributes->set('account', $key->account);

            $requiredScope = match ($request->route()?->getName()) {
                'api.whatsapp.connections' => 'connections:read',
                'api.whatsapp.templates' => 'templates:read',
                'api.whatsapp.templates.sync' => 'templates:sync',
                'api.whatsapp.conversations' => 'conversations:read',
                'api.whatsapp.messages.text',
                'api.whatsapp.messages.template',
                'api.whatsapp.messages.media',
                'api.whatsapp.messages.location',
                'api.whatsapp.messages.list',
                'api.whatsapp.messages.buttons' => 'messages:write',
                default => null,
            };

            if ($requiredScope && ! $key->canUseScope($requiredScope)) {
                return response()->json(['message' => 'API key does not include the required scope.'], 403);
            }
        }

        return $next($request);
    }
}
