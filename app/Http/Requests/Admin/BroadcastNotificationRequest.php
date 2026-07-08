<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BroadcastNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target' => ['required', 'string', 'in:all_users,all_riders,all_drivers,selected_users,selected_riders,selected_drivers'],
            'user_ids' => ['required_if:target,selected_users,selected_riders,selected_drivers', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'category' => ['required', 'string', 'in:ride,wallet,payment,review,promotion,admin,system'],
            'priority' => ['required', 'string', 'in:high,normal,low'],
            'channels' => ['required', 'array'],
            'channels.*' => ['string', 'in:push,database'],
        ];
    }
}
