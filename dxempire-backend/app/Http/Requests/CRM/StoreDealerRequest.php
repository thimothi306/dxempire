<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreDealerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:200'],
            'phone'         => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email'         => ['nullable', 'email'],
            'business_name' => ['required', 'string', 'max:200'],
            'gst_number'    => ['nullable', 'string', 'max:20'],
            'state'         => ['nullable', 'string', 'max:100'],
            'pincode'       => ['nullable', 'string', 'max:10'],
            'credit_limit'  => ['nullable', 'numeric', 'min:0'],
            'price_tier'    => ['nullable', 'in:A,B,C'],
        ];
    }
}
