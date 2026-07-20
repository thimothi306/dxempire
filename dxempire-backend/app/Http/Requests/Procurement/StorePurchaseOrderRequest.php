<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            'total_amount'   => ['required', 'numeric', 'min:0'],
            'expected_count' => ['required', 'integer', 'min:1'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
