<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerSOSRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'message' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'file', 'mimes:jpeg,png,jpg,gif', 'max:10240'],
            'audio' => ['nullable', 'file', 'mimes:mp3,wav,aac,ogg,m4a', 'max:20480'],
            'video' => ['nullable', 'file', 'mimes:mp4,mov,avi,webm,wmv', 'max:51200'],
        ];
    }
}
