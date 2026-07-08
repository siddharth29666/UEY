<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_charges' => ['sometimes', 'numeric', 'min:0'],
            'platform_commission' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'driver_commission' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'distance_unit' => ['sometimes', 'string', 'in:km,mi'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'support_email' => ['sometimes', 'email'],
            'support_phone' => ['sometimes', 'string'],
            'referral_bonus' => ['sometimes', 'numeric', 'min:0'],
            'wallet_limit_min' => ['sometimes', 'numeric', 'min:0'],
            'wallet_limit_max' => ['sometimes', 'numeric', 'min:0'],
            'ride_radius' => ['sometimes', 'numeric', 'min:0.1'],
            'minimum_ride_fare' => ['sometimes', 'numeric', 'min:0'],
            'maximum_ride_distance' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
