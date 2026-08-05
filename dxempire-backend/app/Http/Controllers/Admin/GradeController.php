<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Grade;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(Grade::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => ['required', 'string', 'max:10', 'unique:grades,code'],
            'label'      => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? ((Grade::max('sort_order') ?? 0) + 1);

        $grade = Grade::create($data + ['is_active' => true]);

        return $this->created($grade, 'Grade created.');
    }

    public function update(Request $request, Grade $grade): JsonResponse
    {
        $data = $request->validate([
            'code'       => ['sometimes', 'string', 'max:10', Rule::unique('grades', 'code')->ignore($grade->id)],
            'label'      => ['sometimes', 'string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ]);

        // Renaming the code would orphan every existing product already graded
        // with the old code — block it once any product actually uses this grade.
        if (isset($data['code']) && $data['code'] !== $grade->code && Product::where('grade', $grade->code)->exists()) {
            return $this->error('Cannot rename a grade that is already in use on products. Deactivate it and create a new one instead.', 422);
        }

        $grade->update($data);

        return $this->success($grade->fresh(), 'Grade updated.');
    }

    /** Deactivate rather than hard-delete — existing products keep their grade for history. */
    public function destroy(Grade $grade): JsonResponse
    {
        if (Product::where('grade', $grade->code)->exists()) {
            return $this->error('Cannot deactivate a grade that is still assigned to products.', 422);
        }

        $grade->update(['is_active' => false]);

        return $this->success(null, 'Grade deactivated.');
    }
}
