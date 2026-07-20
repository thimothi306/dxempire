<?php

namespace App\Http\Requests\CRM;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'stage' => ['required', 'in:new,contacted,quoted,negotiating,won,lost'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
