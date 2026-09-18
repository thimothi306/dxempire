<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Traits\ApiResponse;
use App\Http\Traits\Exportable;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    use ApiResponse, Exportable;

    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::with('creator:id,name')
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->from, fn($q) => $q->whereDate('incurred_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('incurred_at', '<=', $request->to))
            ->orderByDesc('incurred_at')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($expenses);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts');
        }

        $expense = Expense::create([
            'category'     => $request->category,
            'amount'       => $request->amount,
            'vendor'       => $request->vendor,
            'description'  => $request->description,
            'incurred_at'  => $request->incurred_at,
            'receipt_path' => $receiptPath,
            'created_by'   => auth()->id(),
        ]);

        return $this->created($expense->load('creator:id,name'), 'Expense recorded.');
    }

    public function show(Expense $expense): JsonResponse
    {
        return $this->success($expense->load('creator:id,name'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense): JsonResponse
    {
        if ($request->hasFile('receipt')) {
            if ($expense->receipt_path) {
                Storage::delete($expense->receipt_path);
            }
            $expense->receipt_path = $request->file('receipt')->store('receipts');
        }

        $expense->update($request->only(['category', 'amount', 'vendor', 'description', 'incurred_at']));

        return $this->success($expense->load('creator:id,name'), 'Expense updated.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        if ($expense->receipt_path) {
            Storage::delete($expense->receipt_path);
        }
        $expense->delete();

        return $this->success(null, 'Expense deleted.');
    }

    public function export(Request $request)
    {
        $expenses = Expense::with('creator:id,name')
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->from, fn($q) => $q->whereDate('incurred_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('incurred_at', '<=', $request->to))
            ->orderByDesc('incurred_at')
            ->get();

        $headers = ['ID', 'Category', 'Description', 'Vendor', 'Amount', 'Date', 'Recorded By'];
        $rows = $expenses->map(fn($e) => [
            $e->id, $e->category, $e->description, $e->vendor ?? '-', $e->amount,
            $e->incurred_at?->format('Y-m-d') ?? '-', $e->creator?->name ?? '-',
        ]);

        $stamp = now()->format('Ymd_His');
        return $request->get('format') === 'pdf'
            ? $this->exportPdf('Expenses', $headers, $rows, "expenses_{$stamp}.pdf")
            : $this->exportCsv("expenses_{$stamp}.csv", $headers, $rows);
    }

    public function categories(): JsonResponse
    {
        $cats = Expense::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return $this->success($cats);
    }
}
