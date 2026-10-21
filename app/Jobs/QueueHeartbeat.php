<?php

namespace App\Jobs;

use App\Models\PlatformSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Tiny job used to prove the cron-driven database queue is processing (Admin > System). */
class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        PlatformSetting::set('system.queue_heartbeat_at', now()->toIso8601String(), 'string', 'system');
    }
}
