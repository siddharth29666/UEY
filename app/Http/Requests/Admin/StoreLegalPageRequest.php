<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegalPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255', 'unique:legal_pages,slug'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'version' => ['sometimes', 'string', 'max:20'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
