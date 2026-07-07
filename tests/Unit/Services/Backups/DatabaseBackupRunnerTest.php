<?php

namespace Tests\Unit\Services\Backups;

use App\Models\BackupSetting;
use App\Services\Backups\DatabaseBackupRunner;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises the real run() flow (dump -> upload -> prune -> record outcome)
 * against a local fake disk instead of S3, and a fake "dump" that just
 * writes a marker file instead of shelling out to pg_dump — neither is
 * available in this environment, and this class is what actually contains
 * the logic worth verifying (retention pruning, path building).
 */
class DatabaseBackupRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_uploads_a_backup_and_marks_success(): void
    {
        Storage::fake('backups-under-test');

        $settings = BackupSetting::current();
        $settings->fill([
            's3_endpoint' => 'https://xxx.r2.cloudflarestorage.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
            's3_path_prefix' => 'minitor-backups',
            'retention_count' => 7,
        ])->save();

        $runner = new class extends DatabaseBackupRunner
        {
            protected function dumpDatabase(string $tmpPath): void
            {
                file_put_contents($tmpPath, 'fake dump contents');
            }

            protected function buildDisk(BackupSetting $settings): Filesystem
            {
                return Storage::disk('backups-under-test');
            }
        };

        $runner->run($settings);

        $settings->refresh();

        $this->assertSame('success', $settings->last_status);
        $this->assertNotNull($settings->last_run_at);
        $this->assertNull($settings->last_error);

        $files = Storage::disk('backups-under-test')->files('minitor-backups');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('minitor-backup-', $files[0]);
    }

    public function test_run_prunes_backups_beyond_the_retention_count(): void
    {
        Storage::fake('backups-under-test');
        $disk = Storage::disk('backups-under-test');

        // Pre-seed 3 "existing" backups, oldest to newest by filename.
        $disk->put('minitor-backups/minitor-backup-2026-01-01_000000.dump', 'old-1');
        $disk->put('minitor-backups/minitor-backup-2026-01-02_000000.dump', 'old-2');
        $disk->put('minitor-backups/minitor-backup-2026-01-03_000000.dump', 'old-3');

        $settings = BackupSetting::current();
        $settings->fill([
            's3_endpoint' => 'https://xxx.r2.cloudflarestorage.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
            's3_path_prefix' => 'minitor-backups',
            'retention_count' => 2,
        ])->save();

        $runner = new class extends DatabaseBackupRunner
        {
            protected function dumpDatabase(string $tmpPath): void
            {
                file_put_contents($tmpPath, 'fake dump contents');
            }

            protected function buildDisk(BackupSetting $settings): Filesystem
            {
                return Storage::disk('backups-under-test');
            }
        };

        $runner->run($settings);

        // 3 pre-seeded + 1 new = 4, retention is 2, so the 2 oldest
        // pre-seeded backups should be gone and the newest 2 remain.
        $remaining = collect($disk->files('minitor-backups'))->sort()->values();

        $this->assertCount(2, $remaining);
        $this->assertStringContainsString('2026-01-03', $remaining[0]);
    }

    public function test_run_records_failure_when_the_dump_step_throws(): void
    {
        Storage::fake('backups-under-test');

        $settings = BackupSetting::current();
        $settings->fill([
            's3_endpoint' => 'https://xxx.r2.cloudflarestorage.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
        ])->save();

        $runner = new class extends DatabaseBackupRunner
        {
            protected function dumpDatabase(string $tmpPath): void
            {
                throw new \RuntimeException('pg_dump failed: connection refused');
            }
        };

        $this->expectException(\RuntimeException::class);

        try {
            $runner->run($settings);
        } finally {
            $settings->refresh();
            $this->assertSame('failed', $settings->last_status);
            $this->assertStringContainsString('connection refused', (string) $settings->last_error);
        }
    }

    public function test_run_records_failure_when_not_configured(): void
    {
        $settings = BackupSetting::current();

        $this->expectException(\RuntimeException::class);

        try {
            (new DatabaseBackupRunner)->run($settings);
        } finally {
            $settings->refresh();
            $this->assertSame('failed', $settings->last_status);
        }
    }
}
