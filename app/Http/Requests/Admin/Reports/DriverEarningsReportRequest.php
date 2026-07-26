<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

class DriverEarningsReportRequest extends FormRequest
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
            'driver_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'sort_by' => ['sometimes', 'string', 'in:net_earnings,completed_rides,gross_earnings,id'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'export' => ['sometimes', 'nullable', 'string', 'in:csv,excel'],
        ];
    }
}
