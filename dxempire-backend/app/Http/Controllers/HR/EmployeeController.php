<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreEmployeeRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    use ApiResponse, Exportable;

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

    public function export(Request $request)
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
            ->get();

        $headers = ['Employee Code', 'Name', 'Phone', 'Department', 'Shift', 'Basic Salary', 'Join Date', 'Active'];
        $rows = $employees->map(fn($e) => [
            $e->employee_code, $e->name ?? $e->user?->name ?? '-', $e->phone ?? $e->user?->phone ?? '-',
            $e->department ?? '-', $e->shift, $e->basic_salary ?? 0, $e->join_date ?? '-', $e->is_active ? 'Yes' : 'No',
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Employees', $headers, $rows, "employees_{$stamp}.pdf")
            : $this->exportCsv("employees_{$stamp}.csv", $headers, $rows);
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
        $employee->load(['user:id,name,phone,email,role', 'payrollItems.payrollRun']);

        return $this->success(array_merge($employee->toArray(), [
            'kyc_documents' => $this->documentChecklist($employee),
        ]));
    }

    /**
     * KYC document uploads for an employee — same document set as the
     * partner registration form, but admin-driven here rather than
     * self-service: added any time after the employee record exists,
     * one file at a time or all together.
     */
    public function uploadDocuments(Request $request, Employee $employee): JsonResponse
    {
        $data = $request->validate([
            'aadhaar_number'          => ['nullable', 'string', 'max:20'],
            'pan_number'              => ['nullable', 'string', 'max:15'],
            'aadhaar_document'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'pan_document'            => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'passport_photo'          => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'education_certificate'   => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'bank_passbook_document'  => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'signed_agreement_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $update = [];
        if (array_key_exists('aadhaar_number', $data)) $update['aadhaar_number'] = $data['aadhaar_number'];
        if (array_key_exists('pan_number', $data)) $update['pan_number'] = $data['pan_number'];

        $dirs = [
            'aadhaar_document' => 'aadhaar', 'pan_document' => 'pan', 'passport_photo' => 'photo',
            'education_certificate' => 'education', 'bank_passbook_document' => 'bank_passbook', 'signed_agreement_document' => 'agreement',
        ];

        foreach ($this->documentColumns() as $field => $column) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $oldPath = $employee->{$column};
            if ($oldPath && Storage::exists($oldPath)) {
                Storage::delete($oldPath);
            }

            $update[$column] = $request->file($field)->store("kyc/employees/{$employee->id}/{$dirs[$field]}");
        }

        if (empty($update)) {
            return $this->error('No document or detail provided to save.', 422);
        }

        $employee->update($update);

        return $this->success([
            'kyc_documents' => $this->documentChecklist($employee->fresh()),
        ], 'Document(s) saved.');
    }

    /** Streams one of an employee's uploaded KYC documents. */
    public function downloadDocument(Employee $employee, string $type): Response|JsonResponse
    {
        $columns = $this->documentColumns();

        if (!isset($columns[$type])) {
            return $this->error('Unknown document type.', 404);
        }

        $path = $employee->{$columns[$type]};

        if (!$path || !Storage::exists($path)) {
            return $this->error('Document not found. It may not have been uploaded yet.', 404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $name = $employee->name ?? $employee->user?->name ?? "employee_{$employee->id}";

        return response(Storage::get($path), 200, [
            'Content-Type'        => Storage::mimeType($path) ?: 'application/octet-stream',
            'Content-Disposition' => "inline; filename=\"{$name}_{$type}.{$extension}\"",
        ]);
    }

    /** @return array<string,string> document type => Employee column holding its storage path */
    private function documentColumns(): array
    {
        return [
            'aadhaar_document'          => 'aadhaar_document_path',
            'pan_document'              => 'pan_document_path',
            'passport_photo'            => 'passport_photo_path',
            'education_certificate'     => 'education_certificate_path',
            'bank_passbook_document'    => 'bank_passbook_path',
            'signed_agreement_document' => 'signed_agreement_path',
        ];
    }

    private function documentChecklist(Employee $employee): array
    {
        return [
            'aadhaar_number' => (bool) $employee->aadhaar_number,
            'pan_number'     => (bool) $employee->pan_number,
            ...collect($this->documentColumns())->mapWithKeys(fn($column, $type) => [$type => (bool) $employee->{$column}])->toArray(),
        ];
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

        // Frontend-only double-entry check, matching bank_account_number — never stored.
        unset($data['confirm_account_number']);

        if (array_key_exists('ifsc_code', $data) && $data['ifsc_code']) {
            $data['ifsc_code'] = strtoupper($data['ifsc_code']);
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
