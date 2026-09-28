<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\Employee;
use App\Models\User;
use App\Services\UniqueCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use ApiResponse, Exportable;

    // b2b_partner is deliberately excluded — partner accounts are created and
    // managed exclusively via Business Partners (New Dealer), which links a
    // Dealer record at the same time. Allowing partner creation/listing here
    // would produce accounts with no Dealer row, invisible everywhere.
    private const ROLES = [
        'super_admin', 'warehouse_staff', 'warehouse_manager', 'qc_engineer',
        'product_manager', 'packing_staff', 'placement_staff',
        'sales', 'accounts', 'hr_manager', 'logistics',
        'state_manager', 'area_manager', 'district_manager',
    ];

    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles:name')
            ->where('role', '!=', 'b2b_partner')
            ->when($request->role, fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $request->role)))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->when($request->search, fn($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($users);
    }

    public function export(Request $request)
    {
        $users = User::with('roles:name')
            ->where('role', '!=', 'b2b_partner')
            ->when($request->role, fn($q) => $q->whereHas('roles', fn($r) => $r->where('name', $request->role)))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->when($request->search, fn($q) => $q
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")
            )
            ->latest()
            ->get();

        $headers = ['Name', 'Phone', 'Email', 'Role', 'Unique Code', 'Active', 'Created'];
        $rows = $users->map(fn($u) => [
            $u->name, $u->phone ?? '-', $u->email ?? '-', $u->role, $u->unique_code ?? '-',
            $u->is_active ? 'Yes' : 'No', $u->created_at->format('Y-m-d'),
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Staff Users', $headers, $rows, "staff_users_{$stamp}.pdf")
            : $this->exportCsv("staff_users_{$stamp}.csv", $headers, $rows);
    }

    public function store(Request $request): JsonResponse
    {
        $createEmployee = $request->boolean('create_employee');

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'phone'               => ['required', 'string', 'unique:users,phone'],
            'email'               => ['nullable', 'email', 'unique:users,email'],
            'password'            => ['nullable', 'string', 'min:6'],
            'role'                => ['required', Rule::in(self::ROLES)],
            'parent_unique_code'  => ['nullable', 'exists:users,unique_code'],
            'is_active'           => ['boolean'],

            // Optional bundled HR/Employee record — same fields as the
            // Employees "Add" form. Only required when create_employee=true,
            // so a plain login-only Staff User isn't forced through them.
            'create_employee'        => ['boolean'],
            'department'              => ['nullable', 'string', 'max:100'],
            'designation'             => ['nullable', 'string', 'max:100'],
            'employment_type'         => ['nullable', 'in:full_time,part_time,contract'],
            'shift'                   => ['nullable', 'in:morning,evening'],
            'salary'                  => [Rule::requiredIf($createEmployee), 'numeric', 'min:0'],
            'joining_date'            => [Rule::requiredIf($createEmployee), 'date'],
            'village_street'          => ['nullable', 'string', 'max:150'],
            'post_office'             => ['nullable', 'string', 'max:100'],
            'police_station'          => ['nullable', 'string', 'max:100'],
            'district'                => ['nullable', 'string', 'max:100'],
            'state'                   => ['nullable', 'string', 'max:100'],
            'pincode'                 => ['nullable', 'string', 'max:10'],
            'bank_account_number'     => [Rule::requiredIf($createEmployee), 'string', 'max:30'],
            'confirm_account_number'  => [Rule::requiredIf($createEmployee), 'same:bank_account_number'],
            'account_holder_name'     => [Rule::requiredIf($createEmployee), 'string', 'max:150'],
            'bank_name'               => [Rule::requiredIf($createEmployee), 'string', 'max:150'],
            'ifsc_code'               => [Rule::requiredIf($createEmployee), 'string', 'max:15'],
        ]);

        DB::beginTransaction();
        try {
            $uniqueCode = UniqueCodeGenerator::generateForRole($data['role']);

            $user = User::create([
                'name'               => $data['name'],
                'phone'              => $data['phone'],
                'email'              => $data['email'] ?? null,
                'password'           => isset($data['password']) ? Hash::make($data['password']) : null,
                'role'               => $data['role'],
                'unique_code'        => $uniqueCode,
                'parent_unique_code' => $data['parent_unique_code'] ?? null,
                'is_active'          => $data['is_active'] ?? true,
            ]);

            $user->assignRole($data['role']);

            $employee = null;
            if ($createEmployee) {
                $employee = Employee::create([
                    'user_id'             => $user->id,
                    'name'                => $data['name'],
                    'phone'               => $data['phone'],
                    'email'               => $data['email'] ?? null,
                    'employee_code'       => Employee::generateEmployeeCode(),
                    'department'          => $data['department'] ?? null,
                    'designation'         => $data['designation'] ?? null,
                    'employment_type'     => $data['employment_type'] ?? 'full_time',
                    'shift'               => $data['shift'] ?? 'morning',
                    'basic_salary'        => $data['salary'],
                    'join_date'           => $data['joining_date'],
                    'is_active'           => true,
                    'village_street'      => $data['village_street'] ?? null,
                    'post_office'         => $data['post_office'] ?? null,
                    'police_station'      => $data['police_station'] ?? null,
                    'district'            => $data['district'] ?? null,
                    'state'               => $data['state'] ?? null,
                    'pincode'             => $data['pincode'] ?? null,
                    'bank_account_number' => $data['bank_account_number'],
                    'account_holder_name' => $data['account_holder_name'],
                    'bank_name'           => $data['bank_name'],
                    'ifsc_code'           => strtoupper($data['ifsc_code']),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->created([
            'user'        => $user->load('roles:name'),
            'unique_code' => $user->unique_code,
            'employee_id' => $employee?->id,
        ], "User created with Unique Code: {$user->unique_code}");
    }

    public function show(User $user): JsonResponse
    {
        return $this->success($user->load(['roles:name', 'permissions:name', 'employee', 'dealer']));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'is_active' => ['boolean'],
        ]);

        $user->update($data);

        return $this->success($user->load('roles:name'), 'User updated.');
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
        ]);

        // Guard: prevent removing the last super_admin
        $currentRoles = $user->getRoleNames();
        if ($currentRoles->contains('super_admin') && $request->role !== 'super_admin') {
            $adminCount = User::whereHas('roles', fn($q) => $q->where('name', 'super_admin'))->count();
            if ($adminCount <= 1) {
                return $this->error('Cannot change role of the last super_admin.', 422);
            }
        }

        $user->syncRoles([$request->role]);
        $user->update(['role' => $request->role]);

        return $this->success($user->load('roles:name'), "Role updated to {$request->role}.");
    }

    public function deactivate(User $user): JsonResponse
    {
        if ($user->id === auth()->id()) {
            return $this->error('You cannot deactivate your own account.', 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return $this->success(null, 'User deactivated and all sessions revoked.');
    }

    public function activate(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);

        return $this->success(null, 'User activated.');
    }

    public function roles(): JsonResponse
    {
        // Deliberately NOT Role::withCount('users') — that resolves the
        // relation on a fresh, unhydrated model instance, which falls back
        // to config('auth.defaults.guard'). Under a real request that's
        // "sanctum" (set by the auth:sanctum middleware), and the sanctum
        // guard has no `provider` configured, so the relation can't resolve
        // a model class and throws. A plain pivot-table count sidesteps
        // guard resolution entirely.
        $counts = DB::table('model_has_roles')
            ->select('role_id', DB::raw('count(*) as count'))
            ->groupBy('role_id')
            ->pluck('count', 'role_id');

        $roles = Role::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'users_count' => $counts[$role->id] ?? 0,
            ]);

        return $this->success($roles);
    }
}
