<?php

namespace App\Jobs;

use App\Models\BackupSetting;
use App\Services\Backups\DatabaseBackupRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a backup off the request cycle so the "Run backup now" button in
 * Settings doesn't hang the browser on a large pg_dump. The scheduled daily
 * backup doesn't need this — it already runs in its own process via
 * `minitor:backup` — this is specifically for the on-demand trigger.
 */
class RunDatabaseBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3700;

    public function handle(DatabaseBackupRunner $runner): void
    {
        // Errors are already recorded onto the BackupSetting row by the
        // runner itself (last_status/last_error), which is what the
        // Settings page polls — no need to rethrow and trigger queue
        // retries/failed_jobs noise for what's an expected, visible failure
        // mode (bad credentials, unreachable bucket, etc.).
        try {
            $runner->run(BackupSetting::current());
        } catch (\Throwable) {
            //
        }
    }
}
