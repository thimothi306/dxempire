<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\Http\Requests\Procurement\UpdateSupplierRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ApiResponse, Exportable;

    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search,    fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate(50);

        return $this->paginated($suppliers);
    }

    public function export(Request $request)
    {
        $suppliers = Supplier::query()
            ->when($request->type,      fn($q) => $q->where('type', $request->type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->search,    fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->get();

        $headers = ['Name', 'Type', 'Phone', 'Email', 'Address', 'GST Number', 'Outstanding Balance', 'Active'];
        $rows = $suppliers->map(fn($s) => [
            $s->name, $s->type, $s->phone ?? '-', $s->email ?? '-', $s->address ?? '-', $s->gst_number ?? '-', $s->outstanding_balance ?? 0, $s->is_active ? 'Yes' : 'No',
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Suppliers', $headers, $rows, "suppliers_{$stamp}.pdf")
            : $this->exportCsv("suppliers_{$stamp}.csv", $headers, $rows);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return $this->created($supplier->toArray());
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->loadCount('purchaseOrders', 'products');

        return $this->success($supplier->toArray());
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return $this->success($supplier->fresh()->toArray(), 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->update(['is_active' => false]);

        return $this->success(null, 'Supplier deactivated.');
    }
}
