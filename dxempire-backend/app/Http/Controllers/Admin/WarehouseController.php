<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::withCount('bins')
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return $this->success($warehouses);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:150'],
            'code'    => ['required', 'string', 'max:20', 'unique:warehouses,code'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'email'   => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
        ]);

        $warehouse = Warehouse::create($data + ['is_active' => true]);

        return $this->created($warehouse, 'Warehouse created.');
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->loadCount('bins');
        $warehouse->load(['bins' => fn($q) => $q->withCount('products')]);

        return $this->success($warehouse);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:150'],
            'code'    => ['sometimes', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouse->id)],
            'phone'   => ['nullable', 'string', 'max:20'],
            'email'   => ['nullable', 'email'],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'is_active' => ['boolean'],
        ]);

        $warehouse->update($data);

        return $this->success($warehouse->fresh(), 'Warehouse updated.');
    }

    /** Make this warehouse the default (used as the fallback pickup location for logistics). */
    public function makeDefault(Warehouse $warehouse): JsonResponse
    {
        Warehouse::where('is_default', true)->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);

        return $this->success($warehouse->fresh(), "{$warehouse->name} set as default warehouse.");
    }

    /** Deactivate rather than hard-delete — bins/products keep referencing it for history. */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->is_default) {
            return $this->error('Cannot deactivate the default warehouse. Set another one as default first.', 422);
        }

        if ($warehouse->bins()->count() > 0) {
            return $this->error('Cannot deactivate a warehouse that still has bins assigned. Move or remove its bins first.', 422);
        }

        $warehouse->update(['is_active' => false]);

        return $this->success(null, 'Warehouse deactivated.');
    }
}
