<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contactId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('emergency_contacts')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                })->ignore($contactId),
            ],
            'relationship' => ['sometimes', 'required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
