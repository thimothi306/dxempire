<?php

namespace App\Http\Requests\QC;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id'      => ['required', 'exists:products,id'],
            'grade'           => ['required_if:outcome,pass', 'nullable', 'in:S1,S2,S3,S4,S5'],
            'condition_notes' => ['nullable', 'string', 'max:1000'],
            'outcome'         => ['required', 'in:pass,repair,reject'],
        ];
    }

    public function messages(): array
    {
        return [
            'grade.required_if' => 'Grade is required when outcome is pass.',
        ];
    }
}
