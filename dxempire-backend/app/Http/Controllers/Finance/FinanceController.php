<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Dealer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    use ApiResponse;

    /**
     * Dealer ledger: full transaction history with running balance.
     */
    public function ledger(Request $request, Dealer $dealer): JsonResponse
    {
        $dealer->loadMissing('user:id,name,phone');

        $orders = Order::where('dealer_id', $dealer->id)
            ->with(['payments', 'invoice'])
            ->when($request->from, fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->orderBy('created_at')
            ->get();

        $totalBilled  = $orders->sum('total_amount');
        $totalPaid    = Payment::whereIn('order_id', $orders->pluck('id'))
            ->where('status', 'captured')
            ->sum('amount');
        $totalRefunded = Payment::whereIn('order_id', $orders->pluck('id'))
            ->where('status', 'refunded')
            ->sum('amount');
        $outstanding = $totalBilled - $totalPaid + $totalRefunded;

        return $this->success([
            'dealer'   => $dealer,
            'summary'  => [
                'total_orders'    => $orders->count(),
                'total_billed'    => round($totalBilled, 2),
                'total_paid'      => round($totalPaid, 2),
                'total_refunded'  => round($totalRefunded, 2),
                'outstanding'     => round($outstanding, 2),
                'credit_limit'    => $dealer->credit_limit,
                'credit_used'     => $dealer->credit_used,
                'credit_available'=> $dealer->availableCredit(),
            ],
            'transactions' => $orders->map(fn($o) => [
                'order_number'   => $o->order_number,
                'date'           => $o->created_at->toDateString(),
                'status'         => $o->status,
                'payment_status' => $o->payment_status,
                'total_amount'   => $o->total_amount,
                'invoice_number' => $o->invoice?->invoice_number,
                'payments'       => $o->payments->map(fn($p) => [
                    'amount' => $p->amount,
                    'status' => $p->status,
                    'method' => $p->method,
                    'paid_at'=> $p->paid_at,
                ]),
            ]),
        ]);
    }

    /**
     * P&L report — revenue vs expenses broken into monthly or quarterly buckets
     * for a given year. Used by the admin dashboard's period-toggle P&L page.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $request->validate([
            'period' => ['nullable', 'in:monthly,quarterly'],
            'year'   => ['nullable', 'integer', 'min:2020', 'max:2099'],
        ]);

        $period = $request->period ?? 'monthly';
        $year   = (int) ($request->year ?? now()->year);
        $key    = "finance.pl.{$period}.{$year}";

        $data = Cache::remember($key, 300, function () use ($period, $year) {
            $revenueByBucket = Order::whereIn('status', ['delivered', 'dispatched'])
                ->whereYear('created_at', $year)
                ->select(DB::raw('MONTH(created_at) as m'), DB::raw('SUM(total_amount) as total'))
                ->groupBy('m')
                ->pluck('total', 'm');

            $expensesByBucket = Expense::whereYear('incurred_at', $year)
                ->select(DB::raw('MONTH(incurred_at) as m'), DB::raw('SUM(amount) as total'))
                ->groupBy('m')
                ->pluck('total', 'm');

            $timeSeries = [];
            if ($period === 'quarterly') {
                for ($q = 1; $q <= 4; $q++) {
                    $months = range(($q - 1) * 3 + 1, $q * 3);
                    $revenue  = collect($months)->sum(fn($m) => (float) ($revenueByBucket[$m] ?? 0));
                    $expenses = collect($months)->sum(fn($m) => (float) ($expensesByBucket[$m] ?? 0));
                    $timeSeries[] = ['period' => "Q{$q} {$year}", 'revenue' => round($revenue, 2), 'expenses' => round($expenses, 2)];
                }
            } else {
                for ($m = 1; $m <= 12; $m++) {
                    $timeSeries[] = [
                        'period'   => date('M', mktime(0, 0, 0, $m, 1)) . " {$year}",
                        'revenue'  => round((float) ($revenueByBucket[$m] ?? 0), 2),
                        'expenses' => round((float) ($expensesByBucket[$m] ?? 0), 2),
                    ];
                }
            }

            $totalRevenue  = round($revenueByBucket->sum(), 2);
            $totalExpenses = round($expensesByBucket->sum(), 2);

            return [
                'year'            => $year,
                'period'          => $period,
                'total_revenue'   => $totalRevenue,
                'total_expenses'  => $totalExpenses,
                'net_profit'      => round($totalRevenue - $totalExpenses, 2),
                'net_margin_pct'  => $totalRevenue > 0 ? round((($totalRevenue - $totalExpenses) / $totalRevenue) * 100, 2) : 0,
                'time_series'     => $timeSeries,
            ];
        });

        return $this->success($data);
    }

    /**
     * GST summary for a single calendar month — invoice-wise CGST/SGST breakup,
     * used for GST filing. `month` is "YYYY-MM" (matches the frontend's
     * <input type="month">).
     */
    public function gstSummary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        [$year, $month] = explode('-', $request->month);
        $key = "finance.gst.{$year}.{$month}";

        $data = Cache::remember($key, 300, function () use ($year, $month) {
            $invoices = Invoice::whereYear('issued_at', $year)
                ->whereMonth('issued_at', $month)
                ->with(['dealer:id,business_name,gst_number', 'order:id,billing_state'])
                ->orderByDesc('issued_at')
                ->get()
                ->map(function ($invoice) {
                    $buyerState = $invoice->billing_state ?? $invoice->order?->billing_state ?? $invoice->dealer?->state;
                    $split = app(OrderService::class)->calculateGstSplit((float) $invoice->gst_amount, $buyerState);

                    return [
                        'id'              => $invoice->id,
                        'invoice_number'  => $invoice->invoice_number,
                        'dealer_name'     => $invoice->dealer?->business_name,
                        'dealer_gstin'    => $invoice->dealer?->gst_number,
                        'taxable_value'   => round((float) $invoice->subtotal, 2),
                        'cgst'            => $split['cgst_amount'],
                        'sgst'            => $split['sgst_amount'],
                        'igst'            => $split['igst_amount'],
                        'total_amount'    => round((float) $invoice->total, 2),
                    ];
                });

            return [
                'month'         => "{$year}-{$month}",
                'taxable_value' => round($invoices->sum('taxable_value'), 2),
                'cgst'          => round($invoices->sum('cgst'), 2),
                'sgst'          => round($invoices->sum('sgst'), 2),
                'igst'          => round($invoices->sum('igst'), 2),
                'invoices'      => $invoices->values(),
            ];
        });

        return $this->success($data);
    }

    /**
     * Accounts receivable: all dealers with outstanding balances.
     */
    public function receivables(): JsonResponse
    {
        $dealers = Dealer::with('user:id,name,phone')
            ->where('credit_used', '>', 0)
            ->get()
            ->map(fn($d) => [
                'dealer_id'       => $d->id,
                'business_name'   => $d->business_name,
                'contact'         => $d->user?->name,
                'phone'           => $d->user?->phone,
                'credit_limit'    => $d->credit_limit,
                'credit_used'     => $d->credit_used,
                'credit_available'=> $d->availableCredit(),
                'utilisation_pct' => $d->credit_limit > 0
                    ? round(($d->credit_used / $d->credit_limit) * 100, 1)
                    : 0,
            ])
            ->sortByDesc('credit_used')
            ->values();

        return $this->success([
            'total_outstanding' => round($dealers->sum('credit_used'), 2),
            'dealers'           => $dealers,
        ]);
    }

    /**
     * GST summary as a downloadable CSV (opens fine in Excel/Sheets).
     * Implemented without maatwebsite/excel — the version pinned in
     * composer.json (^1.1) predates the Concerns\FromCollection interface
     * this codebase's export classes were written against, so it 500s.
     * A native CSV avoids that broken dependency entirely.
     */
    public function gstExport(Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        [$year, $month] = explode('-', $request->month);

        $invoices = Invoice::whereYear('issued_at', $year)
            ->whereMonth('issued_at', $month)
            ->with(['dealer:id,business_name,gst_number', 'order:id,billing_state'])
            ->orderBy('issued_at')
            ->get();

        $orderService = app(OrderService::class);
        $filename = 'gst_summary_' . $request->month . '.csv';

        return response()->streamDownload(function () use ($invoices, $orderService) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice #', 'Date', 'Dealer', 'GSTIN', 'Taxable Value (Rs)', 'CGST (Rs)', 'SGST (Rs)', 'IGST (Rs)', 'Total (Rs)']);

            foreach ($invoices as $invoice) {
                $buyerState = $invoice->billing_state ?? $invoice->order?->billing_state ?? $invoice->dealer?->state;
                $split = $orderService->calculateGstSplit((float) $invoice->gst_amount, $buyerState);

                fputcsv($out, [
                    $invoice->invoice_number,
                    $invoice->issued_at?->toDateString(),
                    $invoice->dealer?->business_name,
                    $invoice->dealer?->gst_number,
                    round((float) $invoice->subtotal, 2),
                    $split['cgst_amount'],
                    $split['sgst_amount'],
                    $split['igst_amount'],
                    round((float) $invoice->total, 2),
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
