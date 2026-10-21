<?php

namespace App\Console\Commands;

use App\Services\OpsMaintenanceService;
use Illuminate\Console\Command;

class OpsBackupDatabaseCommand extends Command
{
    protected $signature = 'ops:backup-db {--force : Force backup now}';

    protected $description = 'Create a compressed database backup.';

    public function handle(OpsMaintenanceService $service): int
    {
        $result = $service->runDatabaseBackup((bool) $this->option('force'));
        $this->line(($result['status'] ?? 'unknown').': '.($result['message'] ?? ''));

        return ($result['status'] ?? '') === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
