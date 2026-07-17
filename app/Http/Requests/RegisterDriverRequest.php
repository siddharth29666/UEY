<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone' => [
                'required',
                'string',
                'min:8',
                'max:20',
                Rule::unique('users', 'phone')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],

            // License
            'license_number' => ['required', 'string', 'max:100', 'unique:driver_profiles,license_number'],
            'license_expiry' => ['required', 'date', 'after:today'],

            // Vehicle details
            'vehicle_make' => ['required', 'string', 'max:50'],
            'vehicle_model' => ['required', 'string', 'max:50'],
            'vehicle_year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'vehicle_color' => ['required', 'string', 'max:30'],
            'vehicle_plate' => ['required', 'string', 'max:20', 'unique:vehicles,plate_number'],
            'vehicle_type_id' => ['required', 'integer', 'exists:vehicle_types,id'],
        ];
    }
}
