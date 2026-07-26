<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLegalPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('legal_pages', 'slug')->ignore($id)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'version' => ['sometimes', 'string', 'max:20'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
