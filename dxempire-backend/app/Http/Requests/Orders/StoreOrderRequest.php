<?php

namespace App\Http\Requests\Orders;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_ids'   => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id'],
            'dealer_id'     => ['nullable', 'integer', 'exists:dealers,id'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'push_token'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_ids.required' => 'At least one product is required.',
            'product_ids.max'      => 'Cannot place an order for more than 50 items at once.',
            'product_ids.*.exists' => 'One or more selected products do not exist.',
        ];
    }
}
