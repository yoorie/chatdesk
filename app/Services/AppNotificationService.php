<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AppNotificationService
{
    public function platform(string $type, string $title, ?string $body = null, string $severity = 'info', ?string $actionUrl = null, array $data = [], ?User $actor = null): AppNotification
    {
        return $this->create(null, 'platform', $type, $title, $body, $severity, $actionUrl, $data, $actor);
    }

    public function workspace(Account|int $account, string $type, string $title, ?string $body = null, string $severity = 'info', ?string $actionUrl = null, array $data = [], ?User $actor = null): AppNotification
    {
        $accountId = $account instanceof Account ? $account->id : $account;

        return $this->create($accountId, 'workspace', $type, $title, $body, $severity, $actionUrl, $data, $actor);
    }

    public function create(?int $accountId, string $scope, string $type, string $title, ?string $body = null, string $severity = 'info', ?string $actionUrl = null, array $data = [], ?User $actor = null): AppNotification
    {
        $dedupeKey = $data['dedupe_key'] ?? null;
        if ($dedupeKey) {
            $existing = AppNotification::query()
                ->where('scope', $scope)
                ->where('type', $type)
                ->where('account_id', $accountId)
                ->whereNull('read_at')
                ->where('created_at', '>=', now()->subHours(6))
                ->where('data->dedupe_key', $dedupeKey)
                ->latest()
                ->first();

            if ($existing) {
                $existing->forceFill([
                    'actor_id' => $actor?->id ?? $existing->actor_id,
                    'severity' => $severity,
                    'title' => $title,
                    'body' => $body,
                    'action_url' => $actionUrl,
                    'data' => array_merge($existing->data ?: [], $data, [
                        'repeat_count' => (int) (($existing->data['repeat_count'] ?? 1)) + 1,
                        'last_seen_at' => now()->toIso8601String(),
                    ]),
                    'updated_at' => now(),
                ])->save();

                return $existing;
            }
        }

        return AppNotification::create([
            'account_id' => $accountId,
            'actor_id' => $actor?->id,
            'scope' => $scope,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    public function auditDestructive(string $action, string $description, ?User $actor = null, ?Account $account = null, ?Model $auditable = null, array $data = [], ?\Illuminate\Http\Request $request = null): void
    {
        \App\Models\DestructiveAuditLog::create([
            'account_id' => $account?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable && method_exists($auditable, 'getKey') ? (string) $auditable->getKey() : null,
            'description' => $description,
            'data' => $data,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
