<?php

namespace Tests\Feature\Settings;

use App\Jobs\RunDatabaseBackupJob;
use App\Models\BackupSetting;
use App\Models\User;
use App\Services\Backups\ConnectionTester;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BackupSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backups_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('backups.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Backups')
                ->where('settings.enabled', false)
                ->where('settings.has_credentials', false),
            );
    }

    public function test_it_saves_s3_credentials_and_reports_them_as_configured(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('backups.update'), [
            'enabled' => '1',
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
            's3_region' => 'auto',
            'retention_count' => 7,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('backups.edit'));

        $settings = BackupSetting::current();

        $this->assertTrue($settings->enabled);
        $this->assertSame('my-bucket', $settings->s3_bucket);
        $this->assertSame('AKIAEXAMPLE', $settings->s3_access_key_id);
        $this->assertSame('secret', $settings->s3_secret_access_key);
        $this->assertTrue($settings->isConfigured());
    }

    public function test_blank_credential_fields_do_not_overwrite_previously_saved_ones(): void
    {
        $user = User::factory()->create();
        BackupSetting::current()->fill([
            'enabled' => true,
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
        ])->save();

        $response = $this->actingAs($user)->put(route('backups.update'), [
            'enabled' => '1',
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'my-bucket-renamed',
            's3_access_key_id' => '',
            's3_secret_access_key' => '',
            'retention_count' => 7,
        ]);

        $response->assertSessionHasNoErrors();

        $settings = BackupSetting::current();

        $this->assertSame('my-bucket-renamed', $settings->s3_bucket);
        $this->assertSame('AKIAEXAMPLE', $settings->s3_access_key_id);
        $this->assertSame('secret', $settings->s3_secret_access_key);
    }

    public function test_enabling_without_a_complete_configuration_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('backups.update'), [
            'enabled' => '1',
            's3_endpoint' => '',
            's3_bucket' => '',
            's3_access_key_id' => '',
            's3_secret_access_key' => '',
            'retention_count' => 7,
        ]);

        $response->assertSessionHasErrors('s3_bucket');
        $this->assertFalse(BackupSetting::current()->enabled);
    }

    public function test_run_now_is_rejected_without_credentials(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('backups.run'));

        $response->assertSessionHasErrors('s3_bucket');
        Queue::assertNothingPushed();
    }

    public function test_run_now_queues_the_backup_job_when_configured(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        BackupSetting::current()->fill([
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
        ])->save();

        $response = $this->actingAs($user)->post(route('backups.run'));

        $response->assertSessionHasNoErrors();
        Queue::assertPushed(RunDatabaseBackupJob::class);
    }

    public function test_test_connection_is_rejected_without_a_bucket_or_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('backups.test-connection'), [
            's3_endpoint' => '',
            's3_bucket' => '',
        ]);

        $response->assertSessionHasErrors('s3_bucket');
    }

    public function test_test_connection_uses_the_submitted_unsaved_values(): void
    {
        $user = User::factory()->create();
        // A different bucket is already saved - the test should use what's
        // in the request, not what's in the DB, since it's meant to check
        // values before they're saved.
        BackupSetting::current()->fill([
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'old-saved-bucket',
            's3_access_key_id' => 'OLDKEY',
            's3_secret_access_key' => 'old-secret',
        ])->save();

        $spy = new SpyConnectionTester;
        $this->app->instance(ConnectionTester::class, $spy);

        $response = $this->actingAs($user)->post(route('backups.test-connection'), [
            's3_endpoint' => 'https://xxx.r2.cloudflarestorage.com',
            's3_bucket' => 'freshly-typed-bucket',
            's3_access_key_id' => 'NEWKEY',
            's3_secret_access_key' => 'new-secret',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('freshly-typed-bucket', $spy->captured['bucket']);
        $this->assertSame('https://xxx.r2.cloudflarestorage.com', $spy->captured['endpoint']);
        $this->assertSame('NEWKEY', $spy->captured['key']);
        $this->assertSame('new-secret', $spy->captured['secret']);
    }

    public function test_test_connection_falls_back_to_the_saved_secret_when_left_blank(): void
    {
        $user = User::factory()->create();
        BackupSetting::current()->fill([
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => 'SAVEDKEY',
            's3_secret_access_key' => 'saved-secret',
        ])->save();

        $spy = new SpyConnectionTester;
        $this->app->instance(ConnectionTester::class, $spy);

        $response = $this->actingAs($user)->post(route('backups.test-connection'), [
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'my-bucket',
            's3_access_key_id' => '',
            's3_secret_access_key' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('SAVEDKEY', $spy->captured['key']);
        $this->assertSame('saved-secret', $spy->captured['secret']);
    }

    public function test_test_connection_reports_a_readable_error_on_failure(): void
    {
        $user = User::factory()->create();

        $this->app->instance(ConnectionTester::class, new class extends ConnectionTester
        {
            public function test(array $config): void
            {
                throw new \RuntimeException('The specified bucket does not exist.');
            }
        });

        $response = $this->actingAs($user)->post(route('backups.test-connection'), [
            's3_endpoint' => 'https://s3.us-east-1.amazonaws.com',
            's3_bucket' => 'does-not-exist',
            's3_access_key_id' => 'AKIAEXAMPLE',
            's3_secret_access_key' => 'secret',
        ]);

        $response->assertSessionHasErrors('s3_bucket');
    }

    public function test_connection_tester_writes_and_deletes_a_probe_object(): void
    {
        Storage::fake('backups-under-test');

        $tester = new class extends ConnectionTester
        {
            /**
             * @param  array<string, mixed>  $config
             */
            protected function buildDisk(array $config): Filesystem
            {
                return Storage::disk('backups-under-test');
            }
        };

        $tester->test([
            'key' => 'AKIAEXAMPLE',
            'secret' => 'secret',
            'region' => 'auto',
            'bucket' => 'my-bucket',
            'endpoint' => 'https://xxx.r2.cloudflarestorage.com',
            'prefix' => 'minitor-backups',
        ]);

        Storage::disk('backups-under-test')->assertMissing('minitor-backups/.minitor-connection-test');
    }
}

/**
 * Records whatever config it was called with instead of touching S3, so
 * tests can assert on exactly what the controller resolved (submitted vs.
 * fallen-back-to-saved values) without a real network call.
 */
class SpyConnectionTester extends ConnectionTester
{
    /** @var array<string, mixed> */
    public array $captured = [];

    public function test(array $config): void
    {
        $this->captured = $config;
    }
}
