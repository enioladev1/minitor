<?php

namespace App\Services\Backups;

use App\Models\BackupSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Dumps the app's Postgres database via `pg_dump` and uploads it to
 * whatever S3-compatible bucket is configured in BackupSetting, then prunes
 * old backups past the configured retention count. Used by both the daily
 * scheduled command and the "run now" button in Settings > Backups.
 */
class DatabaseBackupRunner
{
    /**
     * @throws RuntimeException on any failure — callers decide how to
     *                          surface/log it. Always records the outcome
     *                          on the settings row first, even on failure.
     */
    public function run(BackupSetting $settings): void
    {
        if (! $settings->isConfigured()) {
            $this->recordFailure($settings, 'Backup destination is not configured.');

            throw new RuntimeException('Backup destination is not configured.');
        }

        $tmpPath = storage_path('app/tmp/minitor-backup-'.Str::random(12).'.dump');

        if (! is_dir(dirname($tmpPath))) {
            mkdir(dirname($tmpPath), 0755, true);
        }

        try {
            $this->dumpDatabase($tmpPath);

            $disk = $this->buildDisk($settings);
            $remotePath = $this->remotePath($settings);

            $handle = fopen($tmpPath, 'r');

            if ($handle === false) {
                throw new RuntimeException("Could not open dump file for upload: {$tmpPath}");
            }

            $disk->put($remotePath, $handle);

            $this->pruneOldBackups($disk, $settings);

            $settings->forceFill([
                'last_run_at' => now(),
                'last_status' => 'success',
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            $this->recordFailure($settings, $e->getMessage());

            throw new RuntimeException('Database backup failed: '.$e->getMessage(), previous: $e);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    protected function dumpDatabase(string $tmpPath): void
    {
        $config = config('database.connections.pgsql');

        $process = new Process([
            'pg_dump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--file='.$tmpPath,
            $config['database'],
        ], env: ['PGPASSWORD' => $config['password']], timeout: 3600);

        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput()) ?: 'unknown error';

            throw new RuntimeException("pg_dump failed: {$error}");
        }
    }

    protected function buildDisk(BackupSetting $settings): Filesystem
    {
        return S3BackupDisk::fromSettings($settings);
    }

    private function remotePath(BackupSetting $settings): string
    {
        $prefix = trim((string) $settings->s3_path_prefix, '/');
        $filename = 'minitor-backup-'.now()->format('Y-m-d_His').'.dump';

        return $prefix !== '' ? "{$prefix}/{$filename}" : $filename;
    }

    /**
     * Backups are named so lexicographic order matches chronological order
     * (`minitor-backup-2026-07-07_000000.dump`), so the oldest is simply
     * whatever sorts first once we keep only files under our own prefix.
     */
    private function pruneOldBackups(Filesystem $disk, BackupSetting $settings): void
    {
        $prefix = trim((string) $settings->s3_path_prefix, '/');

        $files = collect($disk->files($prefix ?: null))
            ->filter(fn (string $file) => str_contains(basename($file), 'minitor-backup-'))
            ->sort()
            ->values();

        $excess = $files->count() - max(1, $settings->retention_count);

        if ($excess > 0) {
            $disk->delete($files->take($excess)->all());
        }
    }

    private function recordFailure(BackupSetting $settings, string $message): void
    {
        $settings->forceFill([
            'last_run_at' => now(),
            'last_status' => 'failed',
            'last_error' => $message,
        ])->save();
    }
}
