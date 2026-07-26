<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'password' => ['required', 'string'],
            'fcm_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'device_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'device_type' => ['sometimes', 'nullable', 'string', 'in:android,ios,web'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:50'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
