<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BackupConnectionTestRequest;
use App\Http\Requests\Settings\BackupSettingUpdateRequest;
use App\Jobs\RunDatabaseBackupJob;
use App\Models\BackupSetting;
use App\Services\Backups\ConnectionTester;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BackupController extends Controller
{
    /**
     * Show the backup settings page.
     */
    public function edit(): Response
    {
        $settings = BackupSetting::current();

        return Inertia::render('settings/Backups', [
            'settings' => [
                'enabled' => $settings->enabled,
                's3_endpoint' => $settings->s3_endpoint,
                's3_region' => $settings->s3_region,
                's3_bucket' => $settings->s3_bucket,
                's3_path_prefix' => $settings->s3_path_prefix,
                'retention_count' => $settings->retention_count,
                'has_credentials' => $settings->isConfigured(),
                'last_run_at' => $settings->last_run_at?->toIso8601String(),
                'last_status' => $settings->last_status,
                'last_error' => $settings->last_error,
            ],
        ]);
    }

    /**
     * Update the backup destination/schedule config.
     */
    public function update(BackupSettingUpdateRequest $request): RedirectResponse
    {
        $settings = BackupSetting::current();
        $data = $request->validated();

        // Blank secret fields mean "leave unchanged" - the page never
        // echoes back a previously-saved key/secret to fill them in.
        if (blank($data['s3_access_key_id'] ?? null)) {
            unset($data['s3_access_key_id']);
        }

        if (blank($data['s3_secret_access_key'] ?? null)) {
            unset($data['s3_secret_access_key']);
        }

        $settings->fill($data);

        if ($settings->enabled && ! $settings->isConfigured()) {
            return back()->withErrors([
                's3_bucket' => 'Set an endpoint, bucket, access key, and secret key before enabling backups.',
            ])->withInput();
        }

        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup settings saved.')]);

        return to_route('backups.edit');
    }

    /**
     * Try writing (and immediately deleting) a small marker object using
     * whatever's currently in the form - not necessarily saved yet - so an
     * operator can catch bad credentials before committing to them.
     */
    public function testConnection(BackupConnectionTestRequest $request, ConnectionTester $tester): RedirectResponse
    {
        $data = $request->validated();
        $settings = BackupSetting::current();

        $key = filled($data['s3_access_key_id'] ?? null)
            ? $data['s3_access_key_id']
            : $settings->s3_access_key_id;

        $secret = filled($data['s3_secret_access_key'] ?? null)
            ? $data['s3_secret_access_key']
            : $settings->s3_secret_access_key;

        if (blank($data['s3_endpoint'] ?? null) || blank($data['s3_bucket'] ?? null) || blank($key) || blank($secret)) {
            return back()->withErrors([
                's3_bucket' => 'Set an endpoint, bucket, access key, and secret key first.',
            ]);
        }

        try {
            $tester->test([
                'key' => $key,
                'secret' => $secret,
                'region' => $data['s3_region'] ?? null,
                'bucket' => $data['s3_bucket'],
                'endpoint' => $data['s3_endpoint'],
                'prefix' => $data['s3_path_prefix'] ?? null,
            ]);
        } catch (Throwable $e) {
            return back()->withErrors([
                's3_bucket' => 'Connection failed: '.$e->getMessage(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection successful.')]);

        return back();
    }

    /**
     * Queue an immediate backup run, independent of the daily schedule -
     * lets an operator verify their S3 config works before relying on it.
     */
    public function run(): RedirectResponse
    {
        $settings = BackupSetting::current();

        if (! $settings->isConfigured()) {
            return back()->withErrors([
                's3_bucket' => 'Set an endpoint, bucket, access key, and secret key first.',
            ]);
        }

        RunDatabaseBackupJob::dispatch();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup started - refresh in a moment to see the result.')]);

        return back();
    }
}
