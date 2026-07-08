<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_type' => ['sometimes', 'string', 'max:50'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'device_token' => ['sometimes', 'string', 'max:255'],
            'platform' => ['sometimes', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:100'],
        ];
    }
}
