<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fcm_token' => ['required_without:device_token', 'nullable', 'string', 'max:500'],
            'device_token' => ['required_without:fcm_token', 'nullable', 'string', 'max:500'],
            'device_type' => ['sometimes', 'nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ];
    }
}
