<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Support both standard payload 'latitude'/'longitude' and legacy 'current_latitude'/'current_longitude'
            'current_latitude' => ['required_without:latitude', 'nullable', 'numeric', 'between:-90,90'],
            'current_longitude' => ['required_without:longitude', 'nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['required_without:current_latitude', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_without:current_longitude', 'nullable', 'numeric', 'between:-180,180'],
            'bearing' => ['nullable', 'numeric', 'between:0,360'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'speed' => ['nullable', 'numeric'],
            'accuracy' => ['nullable', 'numeric'],
            'timestamp' => ['nullable'],
        ];
    }
}
