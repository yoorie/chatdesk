<?php

namespace App\Services;

use App\Jobs\DeliverWorkspaceWebhook;
use App\Models\Account;
use App\Models\AccountWebhookEndpoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class WorkspaceWebhookDispatcher
{
    public function dispatch(Account|int $account, string $event, array $data): void
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        $payload = [
            'id' => 'evt_'.str_replace('.', '_', $event).'_'.Str::uuid(),
            'event' => $event,
            'account_id' => $accountId,
            'created_at' => now()->toIso8601String(),
            'data' => $data,
        ];

        $this->endpoints($accountId, $event)
            ->each(fn (AccountWebhookEndpoint $endpoint) => DeliverWorkspaceWebhook::dispatch($endpoint->id, $payload));
    }

    protected function endpoints(int $accountId, string $event): Collection
    {
        return AccountWebhookEndpoint::query()
            ->where('account_id', $accountId)
            ->where('is_enabled', true)
            ->get()
            ->filter(fn (AccountWebhookEndpoint $endpoint) => in_array($event, $endpoint->events ?: [], true))
            ->values();
    }
}
