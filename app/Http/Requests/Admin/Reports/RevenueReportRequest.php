<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

class RevenueReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'start_date' => ['required_with:end_date', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['required_with:start_date', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'year' => ['sometimes', 'integer', 'min:2020', 'max:2099'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'export' => ['sometimes', 'nullable', 'string', 'in:csv,excel'],
        ];
    }
}
