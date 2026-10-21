<?php

namespace App\Modules\WhatsApp\Services;

use App\Models\PlatformSetting;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use Illuminate\Http\Request;

class WebhookSignatureVerifier
{
    public function isStrict(): bool
    {
        return filter_var(config('whatsapp.webhook.strict_signature', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function isValid(Request $request): bool
    {
        if (! $this->hasAnySecret()) {
            return true;
        }

        $secrets = $this->secrets($this->accountIdForRequest($request));
        if ($secrets === []) {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $body = $request->getContent();
        foreach ($secrets as $secret) {
            $expected = 'sha256='.hash_hmac('sha256', $body, $secret);
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve which workspace a webhook targets so that workspace-owned
     * (tenant-supplied) app secrets are only trusted for that workspace.
     * Without this, any tenant who stored their own Meta app secret could
     * forge signed webhooks for every other tenant's phone number.
     */
    public function accountIdForRequest(Request $request): ?int
    {
        $routeConnection = $request->route()?->parameter('connection');
        if ($routeConnection instanceof WhatsAppConnection) {
            return (int) $routeConnection->account_id;
        }
        if (is_scalar($routeConnection) && (string) $routeConnection !== '') {
            $connection = WhatsAppConnection::where('slug', (string) $routeConnection)
                ->orWhere('id', (string) $routeConnection)
                ->first();

            return $connection ? (int) $connection->account_id : null;
        }

        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return null;
        }

        $phoneNumberIds = [];
        $wabaIds = [];
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if (is_scalar($entry['id'] ?? null)) {
                $wabaIds[] = (string) $entry['id'];
            }
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $id = is_array($change) ? ($change['value']['metadata']['phone_number_id'] ?? null) : null;
                if (is_scalar($id) && (string) $id !== '') {
                    $phoneNumberIds[] = (string) $id;
                }
            }
        }

        $query = WhatsAppConnection::query()->where('is_active', true);
        if ($phoneNumberIds !== []) {
            $accountIds = (clone $query)->whereIn('phone_number_id', array_unique($phoneNumberIds))->pluck('account_id')->unique();
        } elseif ($wabaIds !== []) {
            $accountIds = (clone $query)->whereIn('waba_id', array_unique($wabaIds))->pluck('account_id')->unique();
        } else {
            return null;
        }

        // Only trust a tenant secret when the payload unambiguously targets one workspace.
        return $accountIds->count() === 1 ? (int) $accountIds->first() : null;
    }

    /**
     * True when signature verification is configured at all (platform or any workspace secret).
     */
    public function hasAnySecret(): bool
    {
        return $this->secrets(null, true) !== [];
    }

    public function secrets(?int $accountId = null, bool $allAccounts = false): array
    {
        $secrets = [];
        $this->pushSecret($secrets, PlatformSetting::get('whatsapp.meta_app_secret', config('whatsapp.meta.app_secret')));
        $this->pushSecret($secrets, config('whatsapp.meta.app_secret'));

        foreach (explode(',', (string) config('whatsapp.webhook.app_secrets', '')) as $secret) {
            $this->pushSecret($secrets, $secret);
        }

        return array_values(array_unique($secrets));
    }

    protected function pushSecret(array &$secrets, mixed $secret): void
    {
        if (! is_string($secret)) {
            return;
        }

        $secret = trim($secret);
        if ($secret !== '') {
            $secrets[] = $secret;
        }
    }
}
