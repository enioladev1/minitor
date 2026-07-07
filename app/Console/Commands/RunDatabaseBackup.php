<?php

namespace App\Console\Commands;

use App\Models\BackupSetting;
use App\Services\Backups\DatabaseBackupRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('minitor:backup {--force : Run even if backups are disabled in Settings}')]
#[Description('Dump the database and upload it to the configured S3-compatible backup destination.')]
class RunDatabaseBackup extends Command
{
    public function handle(DatabaseBackupRunner $runner): int
    {
        $settings = BackupSetting::current();

        if (! $settings->enabled && ! $this->option('force')) {
            $this->info('Backups are disabled in Settings — skipping.');

            return self::SUCCESS;
        }

        $this->info('Starting database backup...');

        try {
            $runner->run($settings);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Database backup uploaded successfully.');

        return self::SUCCESS;
    }
}
