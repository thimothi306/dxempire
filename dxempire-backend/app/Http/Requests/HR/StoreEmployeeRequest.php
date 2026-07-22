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
        $isUpdate   = (bool) $employeeId;

        return [
            'name'             => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:150'],
            'phone'            => ['nullable', 'string', 'max:20'],
            'email'            => ['nullable', 'email', 'max:150'],
            'user_id'          => ['nullable', 'integer', 'exists:users,id', 'unique:employees,user_id' . ($employeeId ? ",{$employeeId}" : '')],
            'department'       => ['nullable', 'string', 'max:100'],
            'designation'      => ['nullable', 'string', 'max:100'],
            'employment_type'  => ['nullable', 'in:full_time,part_time,contract'],
            'shift'            => ['nullable', 'in:morning,evening'],
            'salary'           => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'joining_date'     => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'is_active'        => ['boolean'],
        ];
    }
}
