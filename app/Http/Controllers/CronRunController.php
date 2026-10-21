<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Modules\Broadcasts\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CronRunController extends Controller
{
    public function __invoke(Request $request, CampaignService $campaignService): JsonResponse
    {
        $configuredToken = (string) PlatformSetting::get('system.external_cron_token', '');
        $providedToken = (string) $request->query('token', '');

        if ($configuredToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json(['ok' => false, 'error' => 'Invalid cron token.'], 403);
        }

        $lock = Cache::lock('external_cron_runner', 55);
        if (! $lock->get()) {
            PlatformSetting::set('system.external_cron_last_status', 'already_running', 'string', 'system');
            PlatformSetting::set('system.external_cron_last_run_at', now()->toIso8601String(), 'string', 'system');

            return response()->json(['ok' => true, 'status' => 'already_running']);
        }

        try {
            Artisan::call('ops:run-maintenance', ['--no-interaction' => true]);
            $maintenanceOutput = trim(Artisan::output());

            $campaignRecovery = $campaignService->recoverStalledCampaigns();

            $queueOutput = null;
            if (config('queue.scheduled_worker', true) && config('queue.default') !== 'sync') {
                Artisan::call('queue:work', [
                    '--queue' => 'campaigns,chatbots,default',
                    '--stop-when-empty' => true,
                    '--max-time' => 40,
                    '--tries' => 3,
                    '--timeout' => 35,
                    '--no-interaction' => true,
                ]);
                $queueOutput = mb_substr(trim(Artisan::output()), -500);
            }

            $response = [
                'ok' => true,
                'status' => 'processed',
                'pending_jobs' => DB::table('jobs')->count(),
                'pending_by_queue' => DB::table('jobs')
                    ->selectRaw('queue, count(*) as count')
                    ->groupBy('queue')
                    ->get(),
                'campaign_recovery' => $campaignRecovery,
                'maintenance_output' => $maintenanceOutput,
                'worker_mode' => $queueOutput !== null ? 'cron_drain' : 'external',
                'queue_output' => $queueOutput,
            ];

            PlatformSetting::set('system.external_cron_last_status', 'processed', 'string', 'system');
            PlatformSetting::set('system.external_cron_last_run_at', now()->toIso8601String(), 'string', 'system');
            PlatformSetting::set('system.external_cron_last_summary', $response, 'json', 'system');
            PlatformSetting::where('key', 'system.external_cron_last_error')->delete();

            return response()->json($response);
        } catch (\Throwable $e) {
            PlatformSetting::set('system.external_cron_last_status', 'failed', 'string', 'system');
            PlatformSetting::set('system.external_cron_last_run_at', now()->toIso8601String(), 'string', 'system');
            PlatformSetting::set('system.external_cron_last_error', $e->getMessage(), 'string', 'system');

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
