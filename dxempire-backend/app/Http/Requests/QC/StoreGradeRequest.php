<?php

namespace App\Http\Requests\QC;

use App\Models\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'product_id'      => ['required', 'exists:products,id'],
            'grade'           => ['required_if:outcome,pass', 'nullable', Rule::in(Grade::activeCodes())],
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
