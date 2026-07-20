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
            ->when($request->search, fn($q) => $q->whereHas('user', fn($u) =>
                $u->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%")
            ))
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($employees);
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return $this->created($employee->load('user:id,name,phone,email,role'), 'Employee created.');
    }

    public function show(Employee $employee): JsonResponse
    {
        return $this->success($employee->load(['user:id,name,phone,email,role', 'payrollItems.payrollRun']));
    }

    public function update(StoreEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());

        return $this->success($employee->load('user:id,name,phone,email'), 'Employee updated.');
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return $this->success(null, 'Employee deactivated.');
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
