<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorPaymentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $payments = DB::table('vendor_payments')
            ->join('suppliers', 'suppliers.id', '=', 'vendor_payments.supplier_id')
            ->select(
                'vendor_payments.*',
                'suppliers.name as supplier_name'
            )
            ->when($request->supplier_id, fn($q) => $q->where('vendor_payments.supplier_id', $request->supplier_id))
            ->when($request->from, fn($q) => $q->whereDate('vendor_payments.paid_at', '>=', $request->from))
            ->when($request->to,   fn($q) => $q->whereDate('vendor_payments.paid_at', '<=', $request->to))
            ->orderByDesc('vendor_payments.paid_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($payments);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id'       => ['required', 'exists:suppliers,id'],
            'amount'            => ['required', 'numeric', 'min:0.01'],
            'method'            => ['required', 'in:cash,bank_transfer,cheque,upi'],
            'reference_number'  => ['nullable', 'string', 'max:100'],
            'note'              => ['nullable', 'string'],
            'paid_at'           => ['nullable', 'date'],
        ]);

        $payment = DB::table('vendor_payments')->insertGetId([
            'supplier_id'      => $request->supplier_id,
            'amount'           => $request->amount,
            'method'           => $request->method,
            'reference_number' => $request->reference_number,
            'note'             => $request->note,
            'paid_at'          => $request->paid_at ?? now(),
            'created_by'       => $request->user()->id,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        return $this->created(
            DB::table('vendor_payments')->find($payment),
            'Vendor payment recorded.'
        );
    }
}
