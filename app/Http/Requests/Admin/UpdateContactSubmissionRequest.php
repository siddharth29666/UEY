<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', 'in:new,read,in_progress,resolved,archived'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
