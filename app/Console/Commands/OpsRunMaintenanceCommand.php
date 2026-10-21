<?php

namespace App\Console\Commands;

use App\Services\OpsMaintenanceService;
use Illuminate\Console\Command;

class OpsRunMaintenanceCommand extends Command
{
    protected $signature = 'ops:run-maintenance
                            {--force-backup : Force backup even if interval not reached}
                            {--force-cleanup : Force cleanup even if interval not reached}';

    protected $description = 'Run maintenance tick: backup checks + retention cleanup.';

    public function handle(OpsMaintenanceService $service): int
    {
        $result = $service->runTick(
            forceBackup: (bool) $this->option('force-backup'),
            forceCleanup: (bool) $this->option('force-cleanup'),
        );

        $this->line('Backup: '.($result['backup']['status'] ?? 'unknown').' - '.($result['backup']['message'] ?? ''));
        $this->line('Cleanup: '.($result['cleanup']['status'] ?? 'unknown').' - '.($result['cleanup']['message'] ?? ''));

        return self::SUCCESS;
    }
}
