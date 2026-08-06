<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(Permission::orderBy('name')->pluck('name'));
    }

    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions:name')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]);

        return $this->success($roles);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        // super_admin must always retain every permission — editing it here
        // could otherwise lock every admin out of the system at once.
        if ($role->name === 'super_admin') {
            return $this->error('super_admin permissions cannot be edited.', 422);
        }

        $data = $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ]);

        $role->syncPermissions($data['permissions']);

        return $this->success([
            'name'        => $role->name,
            'permissions' => $role->permissions()->pluck('name'),
        ], 'Permissions updated.');
    }
}
