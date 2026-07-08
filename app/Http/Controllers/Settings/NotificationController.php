<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingUpdateRequest;
use App\Http\Requests\Settings\NotificationTestRequest;
use App\Mail\TestNotificationMail;
use App\Models\NotificationSetting;
use App\Services\Mail\DynamicMailerFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NotificationController extends Controller
{
    private const SECRET_FIELDS = [
        'smtp_username',
        'smtp_password',
        'postmark_token',
        'resend_api_key',
        'mailgun_secret',
    ];

    /**
     * Show the notification settings page.
     */
    public function edit(): Response
    {
        $settings = NotificationSetting::current();

        return Inertia::render('settings/Notifications', [
            'settings' => [
                'enabled' => $settings->enabled,
                'provider' => $settings->provider,
                'from_address' => $settings->from_address,
                'from_name' => $settings->from_name,
                'notify_email' => $settings->notify_email,
                'notify_on_reopen' => $settings->notify_on_reopen,
                'smtp_host' => $settings->smtp_host,
                'smtp_port' => $settings->smtp_port,
                'smtp_encryption' => $settings->smtp_encryption,
                'mailgun_domain' => $settings->mailgun_domain,
                'mailgun_endpoint' => $settings->mailgun_endpoint,
                'has_smtp_username' => filled($settings->smtp_username),
                'has_smtp_password' => filled($settings->smtp_password),
                'has_postmark_token' => filled($settings->postmark_token),
                'has_resend_api_key' => filled($settings->resend_api_key),
                'has_mailgun_secret' => filled($settings->mailgun_secret),
                'has_credentials' => $settings->isConfigured(),
                'last_test_at' => $settings->last_test_at?->toIso8601String(),
                'last_test_status' => $settings->last_test_status,
                'last_test_error' => $settings->last_test_error,
            ],
        ]);
    }

    /**
     * Update the notification provider/credentials config.
     */
    public function update(NotificationSettingUpdateRequest $request): RedirectResponse
    {
        $settings = NotificationSetting::current();
        $data = $request->validated();

        // Blank secret fields mean "leave unchanged" - the page never
        // echoes back a previously-saved credential to fill them in.
        foreach (self::SECRET_FIELDS as $field) {
            if (blank($data[$field] ?? null)) {
                unset($data[$field]);
            }
        }

        $settings->fill($data);

        if ($settings->enabled && ! $settings->isConfigured()) {
            return back()->withErrors([
                'provider' => 'Choose a provider and fill in its credentials before enabling notifications.',
            ])->withInput();
        }

        $settings->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return to_route('notifications.edit');
    }

    /**
     * Send a test email using whatever's currently in the form - not
     * necessarily saved yet - so an operator can catch bad credentials
     * before committing to them.
     */
    public function sendTest(NotificationTestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $settings = NotificationSetting::current();

        foreach (self::SECRET_FIELDS as $field) {
            if (blank($data[$field] ?? null)) {
                $data[$field] = $settings->{$field};
            }
        }

        $probe = new NotificationSetting($data);
        $probe->provider = $data['provider'] ?? $settings->provider;
        $probe->from_address = $data['from_address'] ?? $settings->from_address;

        if (! $probe->isConfigured()) {
            return back()->withErrors([
                'provider' => 'Choose a provider, a from address, and fill in its credentials first.',
            ]);
        }

        try {
            $mailerName = app(DynamicMailerFactory::class)->apply($probe);
            $recipient = $settings->notify_email ?: $request->user()->email;

            Mail::mailer($mailerName)->to($recipient)->send(new TestNotificationMail);
        } catch (Throwable $e) {
            $settings->forceFill([
                'last_test_at' => now(),
                'last_test_status' => 'error',
                'last_test_error' => $e->getMessage(),
            ])->save();

            return back()->withErrors([
                'provider' => 'Send failed: '.$e->getMessage(),
            ]);
        }

        $settings->forceFill([
            'last_test_at' => now(),
            'last_test_status' => 'success',
            'last_test_error' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test email sent.')]);

        return back();
    }
}
