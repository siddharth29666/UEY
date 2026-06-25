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
            'current_latitude' => ['required', 'numeric', 'between:-90,90'],
            'current_longitude' => ['required', 'numeric', 'between:-180,180'],
            'bearing' => ['nullable', 'numeric', 'between:0,360'],
        ];
    }
}
