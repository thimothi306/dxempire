<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'supplier_id'           => ['required', 'exists:suppliers,id'],
            'purchase_order_id'     => ['nullable', 'exists:purchase_orders,id'],
            'items'                 => ['required', 'array', 'min:1', 'max:200'],
            'items.*.category'      => ['required', 'in:phone,laptop'],
            'items.*.brand'         => ['required', 'string', 'max:100'],
            'items.*.model'         => ['required', 'string', 'max:200'],
            'items.*.purchase_price'=> ['required', 'numeric', 'min:0'],
            'items.*.imei'          => ['nullable', 'string', 'digits:15'],
            'items.*.serial_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
