<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRideStatusRequest extends FormRequest
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
        // Detect target status from input status, or fallback to routing context
        $status = $this->input('status');

        if (empty($status)) {
            if ($this->routeIs('*start*') || $this->is('*/start')) {
                $status = 'in_progress';
            } elseif ($this->routeIs('*complete*') || $this->is('*/complete')) {
                $status = 'completed';
            } elseif ($this->routeIs('*arriving*') || $this->is('*/arriving')) {
                $status = 'arriving';
            } elseif ($this->routeIs('*arrived*') || $this->is('*/arrived')) {
                $status = 'arrived';
            }
        }

        return [
            'status' => ['nullable', 'string', 'in:arriving,arrived,in_progress,completed'],
            'otp' => [
                $status === 'in_progress' ? 'required' : 'nullable',
                'string',
                'size:6',
            ],
            'actual_distance' => [
                $status === 'completed' ? 'required' : 'nullable',
                'numeric',
                'min:0',
            ],
            'actual_duration' => [
                $status === 'completed' ? 'required' : 'nullable',
                'integer',
                'min:0',
            ],
        ];
    }
}
