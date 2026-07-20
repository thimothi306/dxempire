<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:200'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'email'       => ['nullable', 'email', 'max:191'],
            'gst_number'  => ['nullable', 'string', 'max:20'],
            'address'     => ['nullable', 'string'],
            'type'        => ['sometimes', 'in:dealer,importer,buyback_partner'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
