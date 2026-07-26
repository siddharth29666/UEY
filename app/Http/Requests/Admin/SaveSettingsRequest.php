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
            // General Settings
            'app_name' => ['sometimes', 'string', 'max:255'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'contact_email' => ['sometimes', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'string', 'max:50'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:100'],

            // Platform Settings
            'cancellation_charges' => ['sometimes', 'numeric', 'min:0'],
            'platform_commission' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'driver_commission' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'distance_unit' => ['sometimes', 'string', 'in:km,mi'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'support_email' => ['sometimes', 'email'],
            'support_phone' => ['sometimes', 'string'],
            'wallet_limit_min' => ['sometimes', 'numeric', 'min:0'],
            'wallet_limit_max' => ['sometimes', 'numeric', 'min:0'],
            'ride_radius' => ['sometimes', 'numeric', 'min:0.1'],
            'minimum_ride_fare' => ['sometimes', 'numeric', 'min:0'],
            'maximum_ride_distance' => ['sometimes', 'numeric', 'min:0'],

            // Night Charge Configuration
            'night_charge_enabled' => ['sometimes', 'boolean'],
            'night_charge_type' => ['sometimes', 'string', 'in:percentage,flat'],
            'night_charge_value' => ['sometimes', 'numeric', 'min:0'],
            'night_start_time' => ['sometimes', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],
            'night_end_time' => ['sometimes', 'string', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d$/'],

            // Referral Configuration
            'referral_bonus' => ['sometimes', 'numeric', 'min:0'],
            'referral_bonus_referrer' => ['sometimes', 'numeric', 'min:0'],
            'referral_bonus_referred' => ['sometimes', 'numeric', 'min:0'],
            'referral_bonus_driver_referrer' => ['sometimes', 'numeric', 'min:0'],
            'referral_bonus_driver_referred' => ['sometimes', 'numeric', 'min:0'],
            'referral_require_first_ride' => ['sometimes', 'boolean'],
            'referral_expiry_days' => ['sometimes', 'integer', 'min:1'],

            // Promo Defaults Configuration
            'promo_default_usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'promo_default_per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'promo_default_max_discount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'promo_default_min_fare' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'promo_default_first_ride_only' => ['sometimes', 'boolean'],
        ];
    }
}
