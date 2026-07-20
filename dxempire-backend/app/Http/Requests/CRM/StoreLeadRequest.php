<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'source'        => ['required', 'in:b2b_inquiry,website,referral,walk_in,marketplace'],
            'contact_name'  => ['required', 'string', 'max:200'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:200'],
            'notes'         => ['nullable', 'string'],
            'assigned_to'   => ['nullable', 'exists:users,id'],
        ];
    }
}
