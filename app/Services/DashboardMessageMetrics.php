<?php

namespace App\Services;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Cache;

class DashboardMessageMetrics
{
    /**
     * Message counters for the dashboard. "total"/"today"/"this_*" count every message (sent and
     * received); the inbound_* and outbound_* keys split them by direction.
     *
     * Dashboard counters may lag by at most 30 seconds; inbox and permissions stay live.
     */
    public function forAccount(?int $accountId = null): array
    {
        $scope = $accountId === null ? 'platform' : 'workspace:'.$accountId;

        return Cache::remember('dashboard:message-metrics:v2:'.$scope, 30, function () use ($accountId) {
            $now = now();
            $ranges = [
                'today' => [$now->copy()->startOfDay(), $now->copy()->addDay()->startOfDay()],
                'this_week' => [$now->copy()->startOfWeek(), $now->copy()->startOfWeek()->addWeek()],
                'this_month' => [$now->copy()->startOfMonth(), $now->copy()->startOfMonth()->addMonth()],
            ];
            $query = WhatsAppMessage::query()->when($accountId !== null, fn ($query) => $query->where('account_id', $accountId))
                ->selectRaw('COUNT(*) as total');

            foreach ($ranges as $key => [$start, $end]) {
                $query->selectRaw("SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as {$key}", [$start, $end]);
            }
            $conditions = [
                'inbound' => "direction = 'inbound'",
                'outbound' => "direction = 'outbound'",
                'delivered' => "direction = 'outbound' AND status IN ('delivered', 'read')",
                'read' => "direction = 'outbound' AND status = 'read'",
                'failed' => "direction = 'outbound' AND status = 'failed'",
            ];
            foreach ($conditions as $key => $condition) {
                $alias = $query->getQuery()->getGrammar()->wrap($key);
                $query->selectRaw("SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END) as {$alias}");
                [$start, $end] = $ranges['this_month'];
                $query->selectRaw("SUM(CASE WHEN {$condition} AND created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as {$key}_this_month", [$start, $end]);
                if (in_array($key, ['inbound', 'outbound'], true)) {
                    // Sent / received today, so tiles never mix "sent" totals with all-message counts.
                    [$start, $end] = $ranges['today'];
                    $query->selectRaw("SUM(CASE WHEN {$condition} AND created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as {$key}_today", [$start, $end]);
                }
            }

            return array_map('intval', $query->first()->getAttributes());
        });
    }
}
