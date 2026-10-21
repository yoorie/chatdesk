<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SystemBackup;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\Broadcasts\Models\CampaignMessage;
use App\Modules\Chatbots\Models\Bot;
use App\Modules\Chatbots\Models\BotExecution;
use App\Modules\WhatsApp\Models\WhatsAppConnection;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class CronDiagnosticsService
{
    public function deliverySummary(): array
    {
        $now = now();

        $jobsExists = Schema::hasTable('jobs');
        $failedJobsExists = Schema::hasTable('failed_jobs');
        $botExecutionsExists = Schema::hasTable('bot_executions');
        $campaignMessagesExists = Schema::hasTable('campaign_messages');
        $notificationOutboxExists = Schema::hasTable('notification_outbox');
        $systemBackupsExists = Schema::hasTable('system_backups');

        $pendingByQueue = $jobsExists
            ? DB::table('jobs')
                ->select('queue', DB::raw('count(*) as count'))
                ->groupBy('queue')
                ->pluck('count', 'queue')
                ->map(fn ($v) => (int) $v)
                ->toArray()
            : [];
        $pendingTotal = array_sum($pendingByQueue);
        $oldestPending = $jobsExists ? DB::table('jobs')->min('available_at') : null;

        $failedLastHour = $failedJobsExists
            ? DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subHour())->count()
            : 0;
        $failedLast24h = $failedJobsExists
            ? DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subDay())->count()
            : 0;
        $recentFailures = $failedJobsExists
            ? DB::table('failed_jobs')
                ->select(['id', 'queue', 'exception', 'failed_at'])
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    $exception = (string) ($row->exception ?? '');
                    $firstLine = trim(strtok($exception, "\n") ?: 'Unknown error');

                    return [
                        'id' => (string) $row->id,
                        'queue' => (string) ($row->queue ?: 'default'),
                        'error' => mb_substr($firstLine, 0, 220),
                        'failed_at' => $this->isoOrNull($row->failed_at),
                    ];
                })
                ->values()
                ->all()
            : [];

        $mailDriver = (string) config('mail.default', 'log');
        $mailFailures24h = $failedJobsExists
            ? DB::table('failed_jobs')
                ->where('failed_at', '>=', $now->copy()->subDay())
                ->where(function ($q) {
                    $q->where('payload', 'like', '%Illuminate\\\\Notifications%')
                        ->orWhere('payload', 'like', '%Mailable%')
                        ->orWhere('payload', 'like', '%Mail%');
                })
                ->count()
            : 0;
        $notificationFailures24h = $failedJobsExists
            ? DB::table('failed_jobs')
                ->where('failed_at', '>=', $now->copy()->subDay())
                ->where('payload', 'like', '%Illuminate\\\\Notifications\\\\SendQueuedNotifications%')
                ->count()
            : 0;
        $mailFallbackLastTriggeredAt = PlatformSetting::get('mail.fallback.last_triggered_at');
        $mailFallbackLastError = PlatformSetting::get('mail.fallback.last_error');
        $templateDiagnostics = [];
        $recentOutboxFailures = [];

        if ($notificationOutboxExists) {
            $templateDiagnostics = DB::table('notification_outbox')
                ->select([
                    DB::raw("COALESCE(NULLIF(template_key, ''), 'unclassified') as template_key"),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued"),
                    DB::raw("SUM(CASE WHEN status = 'retrying' THEN 1 ELSE 0 END) as retrying"),
                    DB::raw("SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent"),
                    DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed"),
                    DB::raw('MAX(last_attempt_at) as last_attempt_at'),
                ])
                ->where('channel', 'mail')
                ->where('created_at', '>=', $now->copy()->subDays(7))
                ->groupBy(DB::raw("COALESCE(NULLIF(template_key, ''), 'unclassified')"))
                ->orderByDesc('failed')
                ->orderByDesc('total')
                ->limit(12)
                ->get()
                ->map(fn ($row) => [
                    'template_key' => (string) $row->template_key,
                    'total' => (int) $row->total,
                    'queued' => (int) $row->queued,
                    'retrying' => (int) $row->retrying,
                    'sent' => (int) $row->sent,
                    'failed' => (int) $row->failed,
                    'last_attempt_at' => $this->isoOrNull($row->last_attempt_at),
                ])
                ->values()
                ->all();

            $recentOutboxFailures = DB::table('notification_outbox')
                ->select([
                    DB::raw("COALESCE(NULLIF(template_key, ''), 'unclassified') as template_key"),
                    'recipient',
                    'provider_code',
                    'failure_reason',
                    'failed_at',
                ])
                ->where('channel', 'mail')
                ->where('status', 'failed')
                ->orderByDesc('failed_at')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'template_key' => (string) $row->template_key,
                    'recipient' => $row->recipient ? (string) $row->recipient : null,
                    'provider_code' => $row->provider_code ? (string) $row->provider_code : null,
                    'failure_reason' => $row->failure_reason ? mb_substr((string) $row->failure_reason, 0, 240) : null,
                    'failed_at' => $this->isoOrNull($row->failed_at),
                ])
                ->values()
                ->all();
        }

        $chatbotExec24h = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'running' => 0,
            'total' => 0,
        ];
        if ($botExecutionsExists) {
            $rows = DB::table('bot_executions')
                ->select('status', DB::raw('count(*) as count'))
                ->where('created_at', '>=', $now->copy()->subDay())
                ->groupBy('status')
                ->get();
            foreach ($rows as $row) {
                $status = (string) $row->status;
                $count = (int) $row->count;
                if (array_key_exists($status, $chatbotExec24h)) {
                    $chatbotExec24h[$status] = $count;
                }
                $chatbotExec24h['total'] += $count;
            }
        }

        $campaignStats24h = [
            'sent' => 0,
            'delivered' => 0,
            'read' => 0,
            'failed' => 0,
            'total' => 0,
        ];
        if ($campaignMessagesExists) {
            $campaignStats24h['sent'] = (int) DB::table('campaign_messages')
                ->whereNotNull('sent_at')
                ->where('sent_at', '>=', $now->copy()->subDay())
                ->count();
            $campaignStats24h['delivered'] = (int) DB::table('campaign_messages')
                ->whereNotNull('delivered_at')
                ->where('delivered_at', '>=', $now->copy()->subDay())
                ->count();
            $campaignStats24h['read'] = (int) DB::table('campaign_messages')
                ->whereNotNull('read_at')
                ->where('read_at', '>=', $now->copy()->subDay())
                ->count();
            $campaignStats24h['failed'] = (int) DB::table('campaign_messages')
                ->whereNotNull('failed_at')
                ->where('failed_at', '>=', $now->copy()->subDay())
                ->count();
            $campaignStats24h['total'] = (int) DB::table('campaign_messages')
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count();
        }

        $activeConnections = (int) WhatsAppConnection::where('is_active', true)->count();
        $lastWebhookAt = WhatsAppConnection::max('webhook_last_received_at');
        $webhookHealthy = (int) WhatsAppConnection::where('is_active', true)
            ->whereNotNull('webhook_last_received_at')
            ->where('webhook_last_received_at', '>=', $now->copy()->subHour())
            ->count();
        $webhookStale = (int) WhatsAppConnection::where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('webhook_last_received_at')
                    ->orWhere('webhook_last_received_at', '<', $now->copy()->subHour());
            })
            ->count();
        $webhookErrors = (int) WhatsAppConnection::where('is_active', true)
            ->whereNotNull('webhook_last_error')
            ->count();

        $healthScore = 100;
        $healthScore -= min(40, $failedLast24h * 5);
        $healthScore -= min(25, $mailFailures24h * 5);
        $healthScore -= min(20, $chatbotExec24h['failed'] * 2);
        $healthScore -= min(20, $campaignStats24h['failed'] * 2);
        $healthScore -= min(20, $webhookErrors * 5);
        $latestBackup = $systemBackupsExists
            ? SystemBackup::query()->where('type', 'database')->latest('created_at')->first()
            : null;
        if ($latestBackup && $latestBackup->status !== 'completed') {
            $healthScore -= 10;
        }
        $healthScore = max(0, (int) $healthScore);

        return [
            'generated_at' => $now->toIso8601String(),
            'health_score' => $healthScore,
            'queue' => [
                'pending_total' => $pendingTotal,
                'pending_by_queue' => $pendingByQueue,
                'failed_last_hour' => (int) $failedLastHour,
                'failed_last_24h' => (int) $failedLast24h,
                'oldest_pending_at' => $oldestPending ? now()->setTimestamp((int) $oldestPending)->toIso8601String() : null,
            ],
            'mail' => [
                'driver' => $mailDriver,
                'mail_related_failures_last_24h' => (int) $mailFailures24h,
                'notification_failures_last_24h' => (int) $notificationFailures24h,
                'fallback_enabled' => $mailDriver === 'failover',
                'fallback_last_triggered_at' => $this->isoOrNull($mailFallbackLastTriggeredAt),
                'fallback_last_error' => is_string($mailFallbackLastError) ? $mailFallbackLastError : null,
                'template_diagnostics' => $templateDiagnostics,
                'recent_outbox_failures' => $recentOutboxFailures,
            ],
            'triggers' => [
                'chatbots_24h' => $chatbotExec24h,
                'campaigns_24h' => $campaignStats24h,
            ],
            'webhooks' => [
                'active_connections' => $activeConnections,
                'healthy_connections' => $webhookHealthy,
                'stale_connections' => $webhookStale,
                'connections_with_errors' => $webhookErrors,
                'last_webhook_at' => $this->isoOrNull($lastWebhookAt),
            ],
            'backups' => [
                'latest_status' => $latestBackup?->status,
                'latest_completed_at' => $this->isoOrNull($latestBackup?->completed_at),
                'latest_restore_drill_status' => $latestBackup?->restore_drill_status,
                'latest_restore_drill_at' => $this->isoOrNull($latestBackup?->restore_drill_at),
            ],
            'recent_failures' => $recentFailures,
        ];
    }

    public function platformSummary(): array
    {
        $now = now();
        $appPath = base_path();
        $nullRedirect = '>> /dev/null 2>&1';

        $commands = [
            [
                'id' => 'central-worker',
                'title' => 'Supervisor Workers',
                'schedule' => 'always on',
                'description' => 'Persistent Supervisor workers process inbox, chatbot, campaign, mail, and webhook jobs immediately. Cron is only a fallback for maintenance ticks.',
                'command' => "supervisorctl status | grep -i laravel-worker {$nullRedirect}",
            ],
        ];

        $queuePendingDefault = DB::table('jobs')->where('queue', 'default')->count();
        $queuePendingCampaigns = DB::table('jobs')->where('queue', 'campaigns')->count();
        $queuePendingChatbots = DB::table('jobs')->where('queue', 'chatbots')->count();
        $failedQueue24h = DB::table('failed_jobs')->where('failed_at', '>=', $now->copy()->subDay())->count();

        $latestMessageAt = WhatsAppMessage::max('created_at');
        $openConversations = WhatsAppConversation::where('status', 'open')->count();
        $inbound24h = WhatsAppMessage::where('direction', 'inbound')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        $activeConnections = WhatsAppConnection::where('is_active', true)->count();
        $lastWebhookAt = WhatsAppConnection::max('webhook_last_received_at');
        $staleWebhookConnections = WhatsAppConnection::where('is_active', true)
            ->where(function ($query) use ($now) {
                $query->whereNull('webhook_last_received_at')
                    ->orWhere('webhook_last_received_at', '<', $now->copy()->subDay());
            })
            ->count();
        $webhookErrors = WhatsAppConnection::whereNotNull('webhook_last_error')->count();

        $campaignsSending = Campaign::where('status', 'sending')->count();
        $campaignsScheduled = Campaign::where('status', 'scheduled')->count();
        $campaignLastMessageAt = CampaignMessage::max('created_at');

        $activeBots = Bot::where('status', 'active')->count();
        $failedBotExecutions24h = BotExecution::where('status', 'failed')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();
        $lastBotExecutionAt = BotExecution::max('created_at');

        $activeSubscriptions = Subscription::whereIn('status', ['active', 'trialing'])->count();
        $pastDueSubscriptions = Subscription::where('status', 'past_due')->count();
        $canceledSubscriptions = Subscription::where('status', 'canceled')->count();
        $latestSubscriptionChangeAt = Subscription::max('updated_at');

        return [
            'scope' => 'platform',
            'timezone' => config('app.timezone'),
            'commands' => $commands,
            'statuses' => [
                [
                    'key' => 'queue',
                    'label' => 'Queue Worker',
                    'status' => $this->statusFromQueue($queuePendingDefault + $queuePendingCampaigns + $queuePendingChatbots, $failedQueue24h),
                    'last_activity_at' => $this->latestDate([$latestMessageAt, $lastBotExecutionAt, $campaignLastMessageAt]),
                    'metrics' => [
                        'pending_default' => $queuePendingDefault,
                        'pending_chatbots' => $queuePendingChatbots,
                        'pending_campaigns' => $queuePendingCampaigns,
                        'failed_last_24h' => $failedQueue24h,
                    ],
                ],
                [
                    'key' => 'inbox',
                    'label' => 'Inbox',
                    'status' => $openConversations > 0 ? 'healthy' : 'warning',
                    'last_activity_at' => $this->isoOrNull($latestMessageAt),
                    'metrics' => [
                        'open_conversations' => $openConversations,
                        'inbound_last_24h' => $inbound24h,
                    ],
                ],
                [
                    'key' => 'webhooks',
                    'label' => 'Webhooks',
                    'status' => $this->statusFromWebhook($activeConnections, $staleWebhookConnections, $webhookErrors),
                    'last_activity_at' => $this->isoOrNull($lastWebhookAt),
                    'metrics' => [
                        'active_connections' => $activeConnections,
                        'stale_connections' => $staleWebhookConnections,
                        'connections_with_error' => $webhookErrors,
                    ],
                ],
                [
                    'key' => 'campaigns',
                    'label' => 'Campaigns',
                    'status' => $failedQueue24h > 0 ? 'warning' : 'healthy',
                    'last_activity_at' => $this->isoOrNull($campaignLastMessageAt),
                    'metrics' => [
                        'sending' => $campaignsSending,
                        'scheduled' => $campaignsScheduled,
                        'pending_queue' => $queuePendingCampaigns,
                    ],
                ],
                [
                    'key' => 'chatbots',
                    'label' => 'Chatbots',
                    'status' => $this->statusFromChatbots($activeBots, $failedBotExecutions24h),
                    'last_activity_at' => $this->isoOrNull($lastBotExecutionAt),
                    'metrics' => [
                        'active_bots' => $activeBots,
                        'pending_queue' => $queuePendingChatbots,
                        'failed_executions_last_24h' => $failedBotExecutions24h,
                    ],
                ],
                [
                    'key' => 'subscriptions',
                    'label' => 'Subscriptions',
                    'status' => $pastDueSubscriptions > 0 ? 'warning' : 'healthy',
                    'last_activity_at' => $this->isoOrNull($latestSubscriptionChangeAt),
                    'metrics' => [
                        'active_or_trialing' => $activeSubscriptions,
                        'past_due' => $pastDueSubscriptions,
                        'canceled' => $canceledSubscriptions,
                    ],
                ],
            ],
            'log_files' => [
                'laravel' => $this->logMeta('laravel.log'),
                'chatbots' => $this->logMeta('chatbots.log'),
                'whatsapp' => $this->logMeta('whatsapp.log'),
            ],
        ];
    }

    protected function statusFromQueue(int $pendingTotal, int $failedLast24h): string
    {
        if ($failedLast24h > 0) {
            return 'warning';
        }

        if ($pendingTotal > 250) {
            return 'critical';
        }

        if ($pendingTotal > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function statusFromWebhook(int $activeConnections, int $staleConnections, int $errorConnections): string
    {
        if ($activeConnections === 0) {
            return 'warning';
        }

        if ($errorConnections > 0 || $staleConnections > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function statusFromChatbots(int $activeBots, int $failedExecutions24h): string
    {
        if ($activeBots === 0) {
            return 'warning';
        }

        if ($failedExecutions24h > 0) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function logMeta(string $filename): array
    {
        $path = storage_path('logs/'.$filename);

        if (! File::exists($path)) {
            return [
                'exists' => false,
                'path' => $path,
                'last_modified_at' => null,
            ];
        }

        return [
            'exists' => true,
            'path' => $path,
            'last_modified_at' => now()->setTimestamp(File::lastModified($path))->toIso8601String(),
        ];
    }

    protected function latestDate(array $values): ?string
    {
        $normalized = collect($values)
            ->filter()
            ->map(fn ($value) => $this->toTimestamp($value))
            ->filter()
            ->max();

        if (! $normalized) {
            return null;
        }

        return now()->setTimestamp($normalized)->toIso8601String();
    }

    protected function isoOrNull(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function toTimestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        try {
            return Carbon::parse((string) $value)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }
}
