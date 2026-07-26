<?php

namespace App\Http\Requests\Admin\Reports;

use Illuminate\Foundation\Http\FormRequest;

class WalletReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_id' => ['sometimes', 'nullable', 'integer', 'exists:wallets,id'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'type' => ['sometimes', 'nullable', 'string', 'in:credit,debit'],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'export' => ['sometimes', 'nullable', 'string', 'in:csv,excel'],
        ];
    }
}
