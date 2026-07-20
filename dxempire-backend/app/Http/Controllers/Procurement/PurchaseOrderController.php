<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Traits\ApiResponse;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $orders = PurchaseOrder::with('supplier', 'creator')
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->orderByDesc('created_at')
            ->paginate(50);

        return $this->paginated($orders);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $po = PurchaseOrder::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]);

        return $this->created($po->load('supplier', 'creator')->toArray());
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load('supplier', 'creator', 'products');

        return $this->success($purchaseOrder->toArray());
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:draft,placed,received'],
        ]);

        if ($purchaseOrder->status === 'received') {
            return $this->error('Cannot update a fully received purchase order.', 422);
        }

        $purchaseOrder->update(['status' => $request->status]);

        return $this->success($purchaseOrder->fresh()->toArray(), 'Purchase order updated.');
    }
}
