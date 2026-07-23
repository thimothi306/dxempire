<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PetiTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetiTransferController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $transfers = PetiTransfer::with(['createdBy:id,name', 'approvedBy:id,name', 'toDealer:id,business_name'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type,   fn($q) => $q->where('type', $request->type))
            ->when($request->from,   fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,     fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type'          => ['required', 'in:internal,dealer'],
            'from_location' => ['nullable', 'string', 'max:200'],
            'to_location'   => ['nullable', 'required_if:type,internal', 'string', 'max:200'],
            'to_dealer_id'  => ['nullable', 'required_if:type,dealer', 'exists:dealers,id'],
            'items'         => ['required', 'array', 'min:1'],
            'items.*.category'       => ['required', 'in:phone,laptop'],
            'items.*.brand'          => ['required', 'string'],
            'items.*.model'          => ['required', 'string'],
            'items.*.grade'          => ['required', 'in:S1,S2,S3,S4,S5'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'notes'         => ['nullable', 'string'],
        ]);

        $items = $request->items;
        $totalUnits = array_sum(array_column($items, 'quantity'));
        $totalValue = array_sum(array_map(fn($i) => $i['quantity'] * $i['unit_price'], $items));

        $transfer = PetiTransfer::create([
            'transfer_number' => PetiTransfer::generateTransferNumber(),
            'type'            => $request->type,
            'from_location'   => $request->from_location,
            'to_location'     => $request->to_location,
            'to_dealer_id'    => $request->to_dealer_id,
            'items'           => $items,
            'total_units'     => $totalUnits,
            'total_value'     => $totalValue,
            'notes'           => $request->notes,
            'status'          => 'draft',
            'created_by'      => $request->user()->id,
        ]);

        return $this->created($transfer->load(['createdBy:id,name', 'toDealer:id,business_name']), 'Peti transfer created.');
    }

    public function show(PetiTransfer $petiTransfer): JsonResponse
    {
        return $this->success($petiTransfer->load(['createdBy:id,name', 'approvedBy:id,name', 'toDealer:id,business_name,gst_number']));
    }

    public function approve(PetiTransfer $petiTransfer): JsonResponse
    {
        if ($petiTransfer->status !== 'draft') {
            return $this->error("Only draft transfers can be approved. Current status: {$petiTransfer->status}.", 422);
        }

        $petiTransfer->update([
            'status'      => 'approved',
            'approved_by' => request()->user()->id,
        ]);

        return $this->success($petiTransfer->fresh(), 'Transfer approved.');
    }

    public function complete(PetiTransfer $petiTransfer): JsonResponse
    {
        if ($petiTransfer->status !== 'approved') {
            return $this->error("Only approved transfers can be completed. Current status: {$petiTransfer->status}.", 422);
        }

        $petiTransfer->update([
            'status'         => 'completed',
            'transferred_at' => now(),
        ]);

        return $this->success($petiTransfer->fresh(), 'Transfer completed.');
    }

    public function cancel(PetiTransfer $petiTransfer): JsonResponse
    {
        if ($petiTransfer->status === 'completed') {
            return $this->error('Completed transfers cannot be cancelled.', 422);
        }

        $petiTransfer->update(['status' => 'cancelled']);

        return $this->success(null, 'Transfer cancelled.');
    }
}
