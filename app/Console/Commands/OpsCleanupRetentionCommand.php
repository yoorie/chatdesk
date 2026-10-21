<?php

namespace App\Console\Commands;

use App\Services\OpsMaintenanceService;
use Illuminate\Console\Command;

class OpsCleanupRetentionCommand extends Command
{
    protected $signature = 'ops:cleanup-retention {--force : Force cleanup now}';

    protected $description = 'Cleanup old operational data and purge soft-deleted records after recovery window.';

    public function handle(OpsMaintenanceService $service): int
    {
        $result = $service->runRetentionCleanup((bool) $this->option('force'));
        $this->line(($result['status'] ?? 'unknown').': '.($result['message'] ?? ''));

        if (! empty($result['deleted']) && is_array($result['deleted'])) {
            foreach ($result['deleted'] as $key => $count) {
                $this->line(" - {$key}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
