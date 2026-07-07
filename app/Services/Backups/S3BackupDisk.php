<?php

namespace App\Services\Backups;

use App\Models\BackupSetting;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Builds an ad-hoc S3-compatible Flysystem disk from backup config — shared
 * by DatabaseBackupRunner (the real backup) and the "test connection"
 * action (which needs to build the same disk from not-yet-saved form
 * values, before a BackupSetting row reflects them).
 */
class S3BackupDisk
{
    /**
     * @param  array{key: ?string, secret: ?string, region: ?string, bucket: ?string, endpoint: ?string}  $config
     */
    public static function build(array $config): Filesystem
    {
        $endpoint = $config['endpoint'] ?: null;

        return Storage::build([
            'driver' => 's3',
            'key' => $config['key'],
            'secret' => $config['secret'],
            'region' => $config['region'] ?: 'auto',
            'bucket' => $config['bucket'],
            'endpoint' => $endpoint,
            // The endpoint is always required now - even real AWS S3 users
            // type their own regional endpoint, nothing is inferred from a
            // blank field. So this can't key off "is an endpoint set" - it
            // keys off whether it's actually an AWS domain (virtual-hosted-
            // style) or anything else (R2/MinIO/Spaces/custom domains,
            // which almost universally require path-style). This used to
            // be its own user-facing toggle; the two cases never actually
            // disagree with this rule in practice.
            'use_path_style_endpoint' => ! str_contains((string) $endpoint, 'amazonaws.com'),
        ]);
    }

    public static function fromSettings(BackupSetting $settings): Filesystem
    {
        return self::build([
            'key' => $settings->s3_access_key_id,
            'secret' => $settings->s3_secret_access_key,
            'region' => $settings->s3_region,
            'bucket' => $settings->s3_bucket,
            'endpoint' => $settings->s3_endpoint,
        ]);
    }
}
