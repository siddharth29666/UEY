<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price_eur' => 'sometimes|required|numeric|min:0.01',
            'ride_credits' => 'sometimes|required|integer|min:1',
            'duration_days' => 'sometimes|required|integer|min:1',
            'status' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
