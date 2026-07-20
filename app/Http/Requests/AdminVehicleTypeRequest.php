<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminVehicleTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by ability:role:admin middleware or policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $id = $this->route('id') ?: ($this->route('vehicle_type') instanceof \App\Models\VehicleType ? $this->route('vehicle_type')->id : $this->route('vehicle_type'));

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                $id ? 'unique:vehicle_types,name,' . $id : 'unique:vehicle_types,name',
            ],
            'capacity' => ['required', 'integer', 'min:1'],
            'base_fare' => ['required', 'numeric', 'min:0'],
            'per_km_rate' => ['required', 'numeric', 'min:0'],
            'per_minute_rate' => ['required', 'numeric', 'min:0'],
            'minimum_fare' => ['required', 'numeric', 'min:0'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'icon_url' => ['nullable', 'url', 'max:2048'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
