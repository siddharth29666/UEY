<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('emergency_contacts')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],
            'relationship' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
