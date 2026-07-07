<?php

namespace Tests\Unit\Services\Backups;

use App\Services\Backups\S3BackupDisk;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The endpoint is always required now (no more "blank means real AWS S3"),
 * so whether to use path-style addressing has to be derived from the
 * endpoint's own value instead - this locks in that rule.
 */
class S3BackupDiskTest extends TestCase
{
    public function test_a_real_aws_endpoint_uses_virtual_hosted_style(): void
    {
        Storage::shouldReceive('build')
            ->once()
            ->with(\Mockery::on(fn (array $config) => $config['use_path_style_endpoint'] === false))
            ->andReturn(\Mockery::mock(Filesystem::class));

        S3BackupDisk::build([
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret',
            'region' => 'us-east-1',
            'bucket' => 'my-bucket',
            'endpoint' => 'https://s3.us-east-1.amazonaws.com',
        ]);
    }

    public function test_a_non_aws_endpoint_uses_path_style(): void
    {
        Storage::shouldReceive('build')
            ->once()
            ->with(\Mockery::on(fn (array $config) => $config['use_path_style_endpoint'] === true))
            ->andReturn(\Mockery::mock(Filesystem::class));

        S3BackupDisk::build([
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret',
            'region' => 'auto',
            'bucket' => 'my-bucket',
            'endpoint' => 'https://xxx.r2.cloudflarestorage.com',
        ]);
    }
}
