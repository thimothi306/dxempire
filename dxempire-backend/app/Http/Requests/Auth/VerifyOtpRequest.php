<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone'           => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'code'            => ['required', 'string', 'digits:6'],
            'expo_push_token' => ['nullable', 'string'],
            'device_type'     => ['nullable', 'string', 'in:android,ios'],
        ];
    }
}
