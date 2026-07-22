<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price_eur' => 'required|numeric|min:0.01',
            'ride_credits' => 'required|integer|min:1',
            'duration_days' => 'required|integer|min:1',
            'status' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
