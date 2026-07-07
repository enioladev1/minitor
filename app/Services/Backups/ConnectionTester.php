<?php

namespace App\Services\Backups;

use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * Writes (and immediately deletes) a small marker object to prove a set of
 * S3 credentials actually work, without needing a real backup to run.
 * `buildDisk` is a seam tests override to swap in a local fake disk instead
 * of hitting real S3.
 */
class ConnectionTester
{
    /**
     * @param  array{key: ?string, secret: ?string, region: ?string, bucket: ?string, endpoint: ?string, prefix: ?string}  $config
     */
    public function test(array $config): void
    {
        $disk = $this->buildDisk($config);
        $probePath = $this->probePath($config['prefix'] ?? null);

        $disk->put($probePath, 'MINITOR connection test - safe to delete.');
        $disk->delete($probePath);
    }

    /**
     * @param  array{key: ?string, secret: ?string, region: ?string, bucket: ?string, endpoint: ?string, prefix: ?string}  $config
     */
    protected function buildDisk(array $config): Filesystem
    {
        return S3BackupDisk::build([
            'key' => $config['key'],
            'secret' => $config['secret'],
            'region' => $config['region'],
            'bucket' => $config['bucket'],
            'endpoint' => $config['endpoint'],
        ]);
    }

    private function probePath(?string $prefix): string
    {
        $prefix = trim((string) $prefix, '/');

        return ($prefix !== '' ? "{$prefix}/" : '').'.minitor-connection-test';
    }
}
