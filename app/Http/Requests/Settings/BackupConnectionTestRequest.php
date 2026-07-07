<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BackupConnectionTestRequest extends FormRequest
{
    /**
     * Same "blank secret = keep existing" contract as
     * BackupSettingUpdateRequest — the test button submits whatever's
     * currently in the form, which may not have been saved yet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            's3_endpoint' => ['nullable', 'string', 'max:255'],
            's3_region' => ['nullable', 'string', 'max:100'],
            's3_bucket' => ['nullable', 'string', 'max:255'],
            's3_access_key_id' => ['nullable', 'string', 'max:255'],
            's3_secret_access_key' => ['nullable', 'string', 'max:255'],
            's3_path_prefix' => ['nullable', 'string', 'max:255'],
        ];
    }
}
