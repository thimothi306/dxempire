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
            'phone'            => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:20'],
            'email'            => [$isUpdate ? 'sometimes' : 'required', 'email', 'max:150'],
            'user_id'          => ['nullable', 'integer', 'exists:users,id', 'unique:employees,user_id' . ($employeeId ? ",{$employeeId}" : '')],
            'department'       => ['nullable', 'string', 'max:100'],
            'designation'      => ['nullable', 'string', 'max:100'],
            'employment_type'  => ['nullable', 'in:full_time,part_time,contract'],
            'shift'            => ['nullable', 'in:morning,evening'],
            'salary'           => [$isUpdate ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'joining_date'     => [$isUpdate ? 'sometimes' : 'required', 'date'],
            'is_active'        => ['boolean'],
            'incentive_enabled'=> ['boolean'],
            'commission_rate'  => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Address Details — optional, matches the client's form.
            'village_street'   => ['nullable', 'string', 'max:150'],
            'post_office'      => ['nullable', 'string', 'max:100'],
            'police_station'   => ['nullable', 'string', 'max:100'],
            'district'         => ['nullable', 'string', 'max:100'],
            'state'            => ['nullable', 'string', 'max:100'],
            'pincode'          => ['nullable', 'string', 'max:10'],

            // Bank Account Details (Payout & Settlement) — required per the client's form.
            'bank_account_number'    => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:30'],
            'confirm_account_number' => [$isUpdate ? 'sometimes' : 'required', 'same:bank_account_number'],
            'account_holder_name'    => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:150'],
            'bank_name'              => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:150'],
            'ifsc_code'              => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:15'],
        ];
    }
}
