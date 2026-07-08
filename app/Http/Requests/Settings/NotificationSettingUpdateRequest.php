<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NotificationSettingUpdateRequest extends FormRequest
{
    /**
     * Provider-specific secret fields are intentionally optional here even
     * when enabling notifications — the settings page never echoes back a
     * previously-saved secret, so a blank field means "keep what's already
     * stored," not "clear it." The controller enforces that a complete
     * config (new or existing) exists before allowing `enabled`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'provider' => ['nullable', Rule::in(['smtp', 'postmark', 'resend', 'mailgun'])],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'notify_email' => ['nullable', 'email', 'max:255'],
            'notify_on_reopen' => ['required', 'boolean'],

            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl'])],

            'postmark_token' => ['nullable', 'string', 'max:255'],

            'resend_api_key' => ['nullable', 'string', 'max:255'],

            'mailgun_domain' => ['nullable', 'string', 'max:255'],
            'mailgun_secret' => ['nullable', 'string', 'max:255'],
            'mailgun_endpoint' => ['nullable', 'string', 'max:255'],
        ];
    }
}
