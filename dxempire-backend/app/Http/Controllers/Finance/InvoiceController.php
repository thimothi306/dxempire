<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request): JsonResponse
    {
        $invoices = Invoice::with(['order', 'dealer.user'])
            ->when($request->dealer_id, fn($q) => $q->where('dealer_id', $request->dealer_id))
            ->when($request->from, fn($q) => $q->whereDate('issued_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('issued_at', '<=', $request->to))
            ->latest('issued_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($invoices);
    }

    public function generate(Order $order): JsonResponse
    {
        if (!in_array($order->status, ['approved', 'dispatched', 'delivered'])) {
            return $this->error('Invoice can only be generated for approved or dispatched orders.', 422);
        }

        try {
            $invoice = $this->invoiceService->generate($order);
        } catch (\Throwable $e) {
            return $this->error('Invoice generation failed: ' . $e->getMessage(), 500);
        }

        return $this->success($invoice->load('order'), 'Invoice generated.');
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->success($invoice->load(['order.items.product', 'dealer.user']));
    }

    public function recordPayment(Request $request, Invoice $invoice): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,razorpay,cheque,upi'],
            'note'   => ['nullable', 'string'],
        ]);

        $payment = Payment::create([
            'order_id' => $invoice->order_id,
            'amount'   => $request->amount,
            'method'   => $request->method,
            'status'   => 'captured',
            'paid_at'  => now(),
        ]);

        $order = $invoice->order;
        $totalPaid = Payment::where('order_id', $order->id)->where('status', 'captured')->sum('amount');

        if ($totalPaid >= $order->total_amount) {
            $order->update(['payment_status' => 'paid']);
        } elseif ($totalPaid > 0) {
            $order->update(['payment_status' => 'partial']);
        }

        return $this->created($payment, 'Payment recorded.');
    }

    public function download(Invoice $invoice): Response|JsonResponse
    {
        if (!$invoice->pdf_path || !Storage::exists($invoice->pdf_path)) {
            return $this->error('PDF file not found. Please regenerate the invoice.', 404);
        }

        return response(Storage::get($invoice->pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$invoice->invoice_number}.pdf\"",
        ]);
    }
}
