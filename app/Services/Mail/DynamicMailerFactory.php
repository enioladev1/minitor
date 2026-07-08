<?php

namespace App\Services\Mail;

use App\Models\NotificationSetting;
use RuntimeException;

/**
 * Builds a runtime `mail.mailers.dynamic` config from a NotificationSetting
 * row and returns the mailer name to pass to Mail::mailer(). This app has no
 * static per-provider mail config in config/mail.php - the provider and
 * credentials are chosen by the operator in Settings > Notifications and
 * stored encrypted, so the mailer has to be assembled at send time instead.
 *
 * Illuminate\Mail\MailManager::resolve() reads config('mail.mailers.{name}')
 * lazily, so setting this array immediately before Mail::mailer('dynamic')
 * is all that's needed - no service-provider registration required.
 *
 * Must be called fresh inside every job/listener's handle(), never in a
 * service provider's boot() - queue:work doesn't reboot the app between
 * jobs by default, so config set for one job could otherwise leak into the
 * next job on the same worker.
 */
class DynamicMailerFactory
{
    public function apply(NotificationSetting $settings): string
    {
        config(['mail.mailers.dynamic' => match ($settings->provider) {
            'smtp' => [
                'transport' => 'smtp',
                'host' => $settings->smtp_host,
                'port' => $settings->smtp_port,
                'username' => $settings->smtp_username,
                'password' => $settings->smtp_password,
                'encryption' => $settings->smtp_encryption,
            ],
            // Postmark/Resend/Mailgun's Laravel transport factories read
            // these keys directly off the mailer config array itself
            // (Illuminate\Mail\MailManager::createPostmarkTransport /
            // createResendTransport / createMailgunTransport) - no need to
            // also populate config('services.*').
            'postmark' => [
                'transport' => 'postmark',
                'token' => $settings->postmark_token,
            ],
            'resend' => [
                'transport' => 'resend',
                'key' => $settings->resend_api_key,
            ],
            'mailgun' => [
                'transport' => 'mailgun',
                'domain' => $settings->mailgun_domain,
                'secret' => $settings->mailgun_secret,
                'endpoint' => $settings->mailgun_endpoint,
                'scheme' => 'https',
            ],
            default => throw new RuntimeException('No notification email provider configured.'),
        }]);

        config(['mail.from' => [
            'address' => $settings->from_address,
            'name' => $settings->from_name ?: config('app.name'),
        ]]);

        return 'dynamic';
    }
}
