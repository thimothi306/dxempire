<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id;

        return [
            'user_id'      => ['required', 'integer', 'exists:users,id', 'unique:employees,user_id' . ($employeeId ? ",{$employeeId}" : '')],
            'department'   => ['nullable', 'string', 'max:100'],
            'designation'  => ['nullable', 'string', 'max:100'],
            'shift'        => ['required', 'in:morning,evening'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'join_date'    => ['required', 'date'],
            'is_active'    => ['boolean'],
        ];
    }
}
