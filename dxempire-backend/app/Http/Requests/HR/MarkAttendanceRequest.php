<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'records'                => ['required', 'array', 'min:1'],
            'records.*.employee_id'  => ['required', 'integer', 'exists:employees,id'],
            'records.*.date'         => ['required', 'date'],
            'records.*.status'       => ['required', 'in:present,absent,half_day,leave'],
            'records.*.check_in'     => ['nullable', 'date_format:H:i'],
            'records.*.check_out'    => ['nullable', 'date_format:H:i', 'after:records.*.check_in'],
        ];
    }
}
