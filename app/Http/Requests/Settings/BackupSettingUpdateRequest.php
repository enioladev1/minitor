<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BackupSettingUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `s3_access_key_id`/`s3_secret_access_key` are intentionally optional
     * here even when enabling backups — the settings page never echoes
     * back a previously-saved secret, so a blank field means "keep what's
     * already stored," not "clear it." The controller enforces that a
     * complete config (new or existing) exists before allowing `enabled`.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            's3_endpoint' => ['nullable', 'string', 'max:255'],
            's3_region' => ['nullable', 'string', 'max:100'],
            's3_bucket' => ['nullable', 'string', 'max:255'],
            's3_access_key_id' => ['nullable', 'string', 'max:255'],
            's3_secret_access_key' => ['nullable', 'string', 'max:255'],
            's3_path_prefix' => ['nullable', 'string', 'max:255'],
            'retention_count' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }
}
