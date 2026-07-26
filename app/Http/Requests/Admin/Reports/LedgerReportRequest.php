<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

class LedgerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'transaction_type' => ['sometimes', 'nullable', 'string'],
            'reference' => ['sometimes', 'nullable', 'string'],
            'export' => ['sometimes', 'nullable', 'string', 'in:csv,excel'],
        ];
    }
}
