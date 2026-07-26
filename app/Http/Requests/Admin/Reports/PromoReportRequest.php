<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

class PromoReportRequest extends FormRequest
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
            'promo_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'export' => ['sometimes', 'nullable', 'string', 'in:csv,excel'],
        ];
    }
}
