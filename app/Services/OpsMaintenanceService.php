<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Models\SystemBackup;
use App\Modules\Broadcasts\Models\Campaign;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class OpsMaintenanceService
{
    public function __construct(
        protected OperationalAlertService $alertService
    ) {}

    public function runDatabaseBackup(bool $force = false): array
    {
        $lock = Cache::lock('ops:db-backup', 1800);
        if (! $lock->get()) {
            return ['status' => 'skipped', 'message' => 'Backup already running.'];
        }

        $defaultsPath = null;

        try {
            $intervalHours = max(6, (int) PlatformSetting::get('compliance.backup_interval_hours', 24));
            $lastSuccess = SystemBackup::query()
                ->where('type', 'database')
                ->where('status', 'completed')
                ->latest('completed_at')
                ->first();

            if (! $force && $lastSuccess?->completed_at && $lastSuccess->completed_at->gt(now()->subHours($intervalHours))) {
                return ['status' => 'skipped', 'message' => "Last backup is within {$intervalHours}h window."];
            }

            $backup = SystemBackup::create([
                'type' => 'database',
                'status' => 'running',
                'disk' => 'local',
                'started_at' => now(),
            ]);

            $timestamp = now()->format('Ymd-His');
            $relativeDir = 'backups/database/'.now()->format('Y/m/d');
            $absoluteDir = storage_path("app/{$relativeDir}");
            File::ensureDirectoryExists($absoluteDir);
            $relativePath = "{$relativeDir}/db-{$timestamp}.sql.gz";
            $absolutePath = storage_path("app/{$relativePath}");

            $connection = config('database.default', 'mysql');
            $db = config("database.connections.{$connection}");

            $host = (string) ($db['host'] ?? '127.0.0.1');
            $port = (string) ($db['port'] ?? '3306');
            $database = (string) ($db['database'] ?? '');
            $username = (string) ($db['username'] ?? '');
            $password = (string) ($db['password'] ?? '');

            if ($database === '' || $username === '') {
                throw new \RuntimeException('Database backup configuration is incomplete.');
            }

            $defaultsPath = $this->writeMysqlDefaultsFile($host, $port, $username, $password);
            $dumpCommand = sprintf(
                'mysqldump --defaults-extra-file=%s --single-transaction --quick --skip-lock-tables %s',
                escapeshellarg($defaultsPath),
                escapeshellarg($database)
            );
            $command = sprintf(
                'bash -o pipefail -c %s',
                escapeshellarg($dumpCommand.' | gzip > '.escapeshellarg($absolutePath))
            );

            $process = Process::fromShellCommandline($command, base_path(), $password !== '' ? ['MYSQL_PWD' => $password] : []);
            $process->setTimeout(1800);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'mysqldump failed');
            }

            if (! File::exists($absolutePath) || File::size($absolutePath) <= 0) {
                throw new \RuntimeException('Backup file was not created or is empty.');
            }

            $sample = $this->readGzipSample($absolutePath);
            if ($sample === '' || (! str_contains($sample, 'dump') && ! str_contains($sample, 'CREATE TABLE'))) {
                throw new \RuntimeException('Backup file does not look like a valid SQL dump.');
            }

            $this->runRestoreDrill($absolutePath, $backup, $force);

            $backup->update([
                'status' => 'completed',
                'path' => $relativePath,
                'file_size_bytes' => File::size($absolutePath),
                'checksum' => hash_file('sha256', $absolutePath),
                'completed_at' => now(),
            ]);

            $this->pruneBackupFiles();

            return [
                'status' => 'completed',
                'message' => "Backup created: {$relativePath}",
                'backup_id' => $backup->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Database backup failed', ['error' => $e->getMessage()]);
            $this->alertService->send(
                eventKey: 'backup.failed',
                title: 'Database backup failed',
                context: ['error' => $e->getMessage()],
                severity: 'critical'
            );

            SystemBackup::create([
                'type' => 'database',
                'status' => 'failed',
                'started_at' => now(),
                'completed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            return ['status' => 'failed', 'message' => $e->getMessage()];
        } finally {
            if ($defaultsPath && File::exists($defaultsPath)) {
                File::delete($defaultsPath);
            }
            $lock->release();
        }
    }

    public function runRetentionCleanup(bool $force = false): array
    {
        $lock = Cache::lock('ops:retention-cleanup', 600);
        if (! $lock->get()) {
            return ['status' => 'skipped', 'message' => 'Cleanup already running.'];
        }

        try {
            $intervalMinutes = max(30, (int) PlatformSetting::get('compliance.cleanup_interval_minutes', 180));
            $lastRunAtRaw = PlatformSetting::get('compliance.cleanup.last_run_at');
            $lastRunAt = $lastRunAtRaw ? Carbon::parse((string) $lastRunAtRaw) : null;
            if (! $force && $lastRunAt && $lastRunAt->gt(now()->subMinutes($intervalMinutes))) {
                return ['status' => 'skipped', 'message' => "Cleanup ran within {$intervalMinutes} minutes."];
            }

            $retentionDays = max(7, (int) PlatformSetting::get('compliance.data_retention_days', 365));
            $outboxRetentionDays = max(7, (int) PlatformSetting::get('compliance.notification_outbox_retention_days', 45));
            $failedJobsRetentionDays = max(3, (int) PlatformSetting::get('compliance.failed_jobs_retention_days', 14));

            $deleted = [];

            if (DB::getSchemaBuilder()->hasTable('contact_activities')) {
                $deleted['contact_activities'] = DB::table('contact_activities')
                    ->where('created_at', '<', now()->subDays($retentionDays))
                    ->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('support_audit_logs')) {
                $deleted['support_audit_logs'] = DB::table('support_audit_logs')
                    ->where('created_at', '<', now()->subDays($retentionDays))
                    ->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('bot_executions')) {
                $deleted['bot_executions'] = DB::table('bot_executions')
                    ->where('created_at', '<', now()->subDays($retentionDays))
                    ->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('notification_outbox')) {
                $deleted['notification_outbox'] = DB::table('notification_outbox')
                    ->whereIn('status', ['sent', 'failed'])
                    ->where('created_at', '<', now()->subDays($outboxRetentionDays))
                    ->delete();
            }

            if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
                $deleted['failed_jobs'] = DB::table('failed_jobs')
                    ->where('failed_at', '<', now()->subDays($failedJobsRetentionDays))
                    ->delete();
            }

            $deleted['contacts_purged'] = WhatsAppContact::onlyTrashed()
                ->whereNotNull('purge_after_at')
                ->where('purge_after_at', '<=', now())
                ->forceDelete();

            $deleted['campaigns_purged'] = Campaign::onlyTrashed()
                ->whereNotNull('purge_after_at')
                ->where('purge_after_at', '<=', now())
                ->forceDelete();

            PlatformSetting::set('compliance.cleanup.last_run_at', now()->toIso8601String(), 'string', 'compliance');
            PlatformSetting::set('compliance.cleanup.last_summary', json_encode($deleted), 'string', 'compliance');

            return ['status' => 'completed', 'message' => 'Cleanup completed.', 'deleted' => $deleted];
        } catch (\Throwable $e) {
            Log::error('Retention cleanup failed', ['error' => $e->getMessage()]);
            $this->alertService->send(
                eventKey: 'cleanup.failed',
                title: 'Retention cleanup failed',
                context: ['error' => $e->getMessage()],
                severity: 'critical'
            );

            return ['status' => 'failed', 'message' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    public function runTick(bool $forceBackup = false, bool $forceCleanup = false): array
    {
        $backup = $this->runDatabaseBackup($forceBackup);
        $cleanup = $this->runRetentionCleanup($forceCleanup);

        return [
            'backup' => $backup,
            'cleanup' => $cleanup,
        ];
    }

    protected function runRestoreDrill(string $absolutePath, SystemBackup $backup, bool $force = false): void
    {
        $restoreEveryDays = max(1, (int) PlatformSetting::get('compliance.backup_restore_drill_days', 7));

        $lastDrill = SystemBackup::query()
            ->where('type', 'database')
            ->whereNotNull('restore_drill_at')
            ->latest('restore_drill_at')
            ->first();

        if (! $force && $lastDrill?->restore_drill_at && $lastDrill->restore_drill_at->gt(now()->subDays($restoreEveryDays))) {
            return;
        }

        $connection = config('database.default', 'mysql');
        $db = config("database.connections.{$connection}");

        if ($connection === 'mysql') {
            $result = $this->runMysqlRestoreDrill($absolutePath, $db);

            $backup->update([
                'restore_drill_at' => now(),
                'restore_drill_status' => $result['passed'] ? 'passed' : 'failed',
                'error_message' => $result['passed'] ? null : mb_substr((string) ($result['error'] ?? 'Restore drill failed.'), 0, 2000),
                'meta' => array_merge($backup->meta ?? [], [
                    'restore_drill' => $result,
                ]),
            ]);

            return;
        }

        $sample = $this->readGzipSample($absolutePath);
        if ($sample === '') {
            $backup->update([
                'restore_drill_at' => now(),
                'restore_drill_status' => 'failed',
                'error_message' => 'Restore drill failed: could not open gzip file.',
            ]);

            return;
        }

        $passed = trim($sample) !== '';

        $backup->update([
            'restore_drill_at' => now(),
            'restore_drill_status' => $passed ? 'passed' : 'failed',
            'meta' => array_merge($backup->meta ?? [], [
                'restore_drill_sample_prefix' => mb_substr($sample, 0, 200),
            ]),
        ]);
    }

    protected function runMysqlRestoreDrill(string $absolutePath, array $db): array
    {
        $host = (string) ($db['host'] ?? '127.0.0.1');
        $port = (string) ($db['port'] ?? '3306');
        $database = (string) ($db['database'] ?? '');
        $username = (string) ($db['username'] ?? '');
        $password = (string) ($db['password'] ?? '');
        $drillDatabase = 'zyptos_restore_drill_'.now()->format('YmdHis');
        $defaultsPath = null;

        if ($database === '' || $username === '') {
            return [
                'passed' => false,
                'error' => 'Restore drill skipped: database configuration is incomplete.',
            ];
        }

        $defaultsPath = $this->writeMysqlDefaultsFile($host, $port, $username, $password);
        $mysql = sprintf('mysql --defaults-extra-file=%s', escapeshellarg($defaultsPath));

        $run = function (string $command, int $timeout = 1800): Process {
            $process = Process::fromShellCommandline($command, base_path());
            $process->setTimeout($timeout);
            $process->run();

            return $process;
        };

        $created = false;

        try {
            $create = $run($mysql.' --execute='.escapeshellarg(
                "CREATE DATABASE `{$drillDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            ));

            if (! $create->isSuccessful()) {
                throw new \RuntimeException(trim($create->getErrorOutput() ?: $create->getOutput()) ?: 'Could not create restore drill database.');
            }

            $created = true;

            $restoreCommand = sprintf(
                'gzip -dc %s | %s %s',
                escapeshellarg($absolutePath),
                $mysql,
                escapeshellarg($drillDatabase)
            );
            $restore = $run(sprintf(
                'bash -o pipefail -c %s',
                escapeshellarg($restoreCommand)
            ));

            if (! $restore->isSuccessful()) {
                throw new \RuntimeException(trim($restore->getErrorOutput() ?: $restore->getOutput()) ?: 'Could not restore backup into drill database.');
            }

            $tables = $run($mysql.' --batch --skip-column-names '.escapeshellarg($drillDatabase).' --execute='.escapeshellarg('SHOW TABLES'), 120);

            if (! $tables->isSuccessful()) {
                throw new \RuntimeException(trim($tables->getErrorOutput() ?: $tables->getOutput()) ?: 'Could not inspect restored database.');
            }

            $restoredTables = collect(explode("\n", trim($tables->getOutput())))->filter()->values();
            if ($restoredTables->isEmpty()) {
                throw new \RuntimeException('Restore drill database contains no tables.');
            }

            $checkedCounts = [];
            $keyTables = [
                'migrations',
                'users',
                'accounts',
                'whatsapp_connections',
                'whatsapp_contacts',
                'whatsapp_conversations',
                'whatsapp_messages',
                'app_notifications',
            ];

            foreach ($keyTables as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table) || ! $restoredTables->contains($table)) {
                    continue;
                }

                $productionCount = DB::table($table)->count();
                $count = $run($mysql.' --batch --skip-column-names '.escapeshellarg($drillDatabase).' --execute='.escapeshellarg("SELECT COUNT(*) FROM `{$table}`"), 120);

                if (! $count->isSuccessful()) {
                    throw new \RuntimeException(trim($count->getErrorOutput() ?: $count->getOutput()) ?: "Could not count restored table {$table}.");
                }

                $restoredCount = (int) trim($count->getOutput());
                if ($productionCount !== $restoredCount) {
                    throw new \RuntimeException("Restore drill count mismatch for {$table}: production={$productionCount}, restored={$restoredCount}.");
                }

                $checkedCounts[$table] = $restoredCount;
            }

            return [
                'passed' => true,
                'database' => $drillDatabase,
                'restored_tables' => $restoredTables->count(),
                'checked_counts' => $checkedCounts,
                'dropped_after_check' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'passed' => false,
                'database' => $drillDatabase,
                'error' => $e->getMessage(),
                'dropped_after_check' => $created,
            ];
        } finally {
            if ($created) {
                $run($mysql.' --execute='.escapeshellarg("DROP DATABASE IF EXISTS `{$drillDatabase}`"), 120);
            }
            if ($defaultsPath && File::exists($defaultsPath)) {
                File::delete($defaultsPath);
            }
        }
    }

    protected function writeMysqlDefaultsFile(string $host, string $port, string $username, string $password): string
    {
        $path = storage_path('framework/cache/mysql-client-'.bin2hex(random_bytes(8)).'.cnf');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode("\n", [
            '[client]',
            'host='.$this->mysqlDefaultsValue($host),
            'port='.$this->mysqlDefaultsValue($port),
            'user='.$this->mysqlDefaultsValue($username),
            'password='.$this->mysqlDefaultsValue($password),
            '',
        ]));
        @chmod($path, 0600);

        return $path;
    }

    protected function mysqlDefaultsValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    protected function readGzipSample(string $absolutePath): string
    {
        $handle = @gzopen($absolutePath, 'rb');
        if (! $handle) {
            return '';
        }

        $sample = (string) gzread($handle, 4096);
        gzclose($handle);

        return $sample;
    }

    protected function pruneBackupFiles(): void
    {
        $retentionDays = max(3, (int) PlatformSetting::get('compliance.backup_retention_days', 14));
        $threshold = now()->subDays($retentionDays);

        $oldBackups = SystemBackup::query()
            ->where('type', 'database')
            ->where('status', 'completed')
            ->whereNotNull('path')
            ->where('created_at', '<', $threshold)
            ->get();

        foreach ($oldBackups as $backup) {
            $absolutePath = storage_path('app/'.ltrim((string) $backup->path, '/'));
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }
            $backup->delete();
        }
    }
}
