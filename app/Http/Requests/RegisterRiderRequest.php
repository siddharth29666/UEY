<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRiderRequest extends FormRequest
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
        ];
    }
}
