<?php

namespace App\Http\Requests\Admin;

use App\Enums\VehicleStatus;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,approved,rejected'],
            'rejection_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
