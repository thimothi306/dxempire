<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $employees = Employee::with('user:id,name,phone,email,role')
            ->when($request->department, fn($q) => $q->where('department', $request->department))
            ->when($request->shift, fn($q) => $q->where('shift', $request->shift))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->when($request->search, fn($q) => $q->where(function ($qq) use ($request) {
                $qq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('phone', 'like', "%{$request->search}%")
                   ->orWhere('employee_code', 'like', "%{$request->search}%");
            }))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $this->mapFields($request->validated());
        $data['employee_code'] = Employee::generateEmployeeCode();
        $data['shift'] = $data['shift'] ?? 'morning';
        $data['is_active'] = $data['is_active'] ?? true;

        $employee = Employee::create($data);

        return $this->created($employee->load('user:id,name,phone,email,role'), 'Employee created.');
    }

    public function show(Employee $employee): JsonResponse
    {
        return $this->success($employee->load(['user:id,name,phone,email,role', 'payrollItems.payrollRun']));
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($this->mapFields($request->validated()));

        return $this->success($employee->load('user:id,name,phone,email'), 'Employee updated.');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return $this->success(null, 'Employee deactivated.');
    }

    /** Translate the frontend's field names (salary/joining_date) to the underlying DB columns. */
    private function mapFields(array $data): array
    {
        if (array_key_exists('salary', $data)) {
            $data['basic_salary'] = $data['salary'];
            unset($data['salary']);
        }
        if (array_key_exists('joining_date', $data)) {
            $data['join_date'] = $data['joining_date'];
            unset($data['joining_date']);
        }

        return $data;
    }

    public function departments(): JsonResponse
    {
        $depts = Employee::select('department')
            ->whereNotNull('department')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        return $this->success($depts);
    }
}
