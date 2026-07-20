<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
    use ApiResponse;

    private const ROLES = [
        'super_admin', 'warehouse_staff', 'qc_engineer',
        'sales', 'accounts', 'hr_manager', 'b2b_partner', 'logistics',
    ];

    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles:name')
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'phone'               => ['required', 'string', 'unique:users,phone'],
            'email'               => ['nullable', 'email', 'unique:users,email'],
            'password'            => ['nullable', 'string', 'min:6'],
            'role'                => ['required', Rule::in(self::ROLES)],
            'parent_unique_code'  => ['nullable', 'exists:users,unique_code'],
            'is_active'           => ['boolean'],
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

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->created([
            'user' => $user->load('roles:name'),
            'unique_code' => $user->unique_code,
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
        $roles = Role::withCount('users')
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->success($roles);
    }
}
