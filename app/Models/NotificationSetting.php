<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $enabled
 * @property string|null $provider
 * @property string|null $from_address
 * @property string|null $from_name
 * @property string|null $notify_email
 * @property string|null $smtp_host
 * @property int|null $smtp_port
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string|null $smtp_encryption
 * @property string|null $postmark_token
 * @property string|null $resend_api_key
 * @property string|null $mailgun_domain
 * @property string|null $mailgun_secret
 * @property string $mailgun_endpoint
 * @property bool $notify_on_reopen
 * @property Carbon|null $last_test_at
 * @property string|null $last_test_status
 * @property string|null $last_test_error
 */
class NotificationSetting extends Model
{
    protected $fillable = [
        'enabled',
        'provider',
        'from_address',
        'from_name',
        'notify_email',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'postmark_token',
        'resend_api_key',
        'mailgun_domain',
        'mailgun_secret',
        'mailgun_endpoint',
        'notify_on_reopen',
    ];

    protected $hidden = [
        'smtp_username',
        'smtp_password',
        'postmark_token',
        'resend_api_key',
        'mailgun_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'notify_on_reopen' => 'boolean',
            'smtp_username' => 'encrypted',
            'smtp_password' => 'encrypted',
            'postmark_token' => 'encrypted',
            'resend_api_key' => 'encrypted',
            'mailgun_secret' => 'encrypted',
            'last_test_at' => 'datetime',
        ];
    }

    /**
     * There is exactly one row: this app is single-admin/instance-wide, so
     * notification config isn't scoped per-project or per-user - the same
     * pattern as BackupSetting::current().
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([], [
            'enabled' => false,
            'mailgun_endpoint' => 'api.mailgun.net',
            'notify_on_reopen' => true,
        ]);
    }

    public function isConfigured(): bool
    {
        $providerConfigured = match ($this->provider) {
            'smtp' => filled($this->smtp_host) && filled($this->smtp_port),
            'postmark' => filled($this->postmark_token),
            'resend' => filled($this->resend_api_key),
            'mailgun' => filled($this->mailgun_domain) && filled($this->mailgun_secret),
            default => false,
        };

        return $providerConfigured && filled($this->from_address);
    }
}
