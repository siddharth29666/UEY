<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PromoCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $promoId = $this->route('promo_code') ? $this->route('promo_code') : $this->route('id');
        $promoCodeId = is_object($promoId) ? $promoId->id : $promoId;

        return [
            'code' => $promoCodeId ? ['sometimes', 'required', 'string', 'max:50', 'unique:promo_codes,code,'.$promoCodeId] : ['required', 'string', 'max:50', 'unique:promo_codes,code'],
            'discount_type' => ['required', 'string', 'in:percentage,flat'],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'expires_at' => ['required', 'date', 'after:today'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'min_fare' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'first_ride_only' => ['sometimes', 'boolean'],
            'referral_coupon' => ['sometimes', 'boolean'],
            'ride_eligibility' => ['nullable', 'array'],
            'ride_eligibility.*' => ['integer', 'exists:vehicle_types,id'],
        ];
    }
}
