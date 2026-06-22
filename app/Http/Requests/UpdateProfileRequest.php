<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', 'unique:users,email,' . $userId],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            
            // Notifications
            'email_notifications' => ['sometimes', 'boolean'],
            'sms_notifications' => ['sometimes', 'boolean'],
            'push_notifications' => ['sometimes', 'boolean'],
            
            // Driver Specific Settings (only evaluated if authenticated user is a driver)
            'default_navigation' => ['sometimes', 'required', 'string', 'in:google_maps,waze,apple_maps'],
            'auto_accept' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
