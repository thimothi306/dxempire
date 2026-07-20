<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'order_id'    => ['nullable', 'exists:orders,id'],
            'priority'    => ['nullable', 'in:low,medium,high'],
        ];
    }
}
