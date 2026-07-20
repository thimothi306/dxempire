<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class DispatchOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logistics_provider' => ['required', 'string', 'max:100'],
            'awb_number'         => ['required', 'string', 'max:100'],
        ];
    }
}
