<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'message' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:text,image,location'],
            'ride_id' => ['nullable', 'integer', 'exists:rides,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversation_threads,id'],
        ];
    }
}
