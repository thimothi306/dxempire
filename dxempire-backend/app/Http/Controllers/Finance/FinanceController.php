<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Dealer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GstExport;
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
     * P&L report for a date range.
     */
    public function profitLoss(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->from;
        $to   = $request->to;
        $key  = "finance.pl.{$from}.{$to}";

        $data = Cache::remember($key, 300, function () use ($from, $to) {
            // Revenue: sum of delivered order totals in period
            $revenue = Order::whereIn('status', ['delivered', 'dispatched'])
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->sum('total_amount');

            $gstCollected = Order::whereIn('status', ['delivered', 'dispatched'])
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->sum('gst_amount');

            // COGS: sum of purchase_price of sold products in period
            $cogs = Product::where('status', 'sold')
                ->whereBetween('sold_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->sum('purchase_price');

            // Operating expenses
            $expenses = Expense::whereBetween('incurred_at', [$from, $to])->sum('amount');

            // Expense breakdown by category
            $expenseByCategory = Expense::whereBetween('incurred_at', [$from, $to])
                ->select('category', DB::raw('SUM(amount) as total'))
                ->groupBy('category')
                ->orderByDesc('total')
                ->get();

            $grossProfit  = round($revenue - $cogs, 2);
            $netProfit    = round($grossProfit - $expenses, 2);
            $grossMargin  = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 2) : 0;
            $netMargin    = $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0;

            return [
                'period'              => ['from' => $from, 'to' => $to],
                'revenue'             => round($revenue, 2),
                'gst_collected'       => round($gstCollected, 2),
                'revenue_ex_gst'      => round($revenue - $gstCollected, 2),
                'cogs'                => round($cogs, 2),
                'gross_profit'        => $grossProfit,
                'gross_margin_pct'    => $grossMargin,
                'operating_expenses'  => round($expenses, 2),
                'expense_breakdown'   => $expenseByCategory,
                'net_profit'          => $netProfit,
                'net_margin_pct'      => $netMargin,
            ];
        });

        return $this->success($data);
    }

    /**
     * GST summary: total GST collected grouped by month.
     */
    public function gstSummary(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
        ]);

        $year = $request->year;
        $key  = "finance.gst.{$year}";

        $data = Cache::remember($key, 300, function () use ($year) {
            $monthly = Order::whereIn('status', ['delivered', 'dispatched', 'approved'])
                ->whereYear('created_at', $year)
                ->select(
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(subtotal) as taxable_value'),
                    DB::raw('SUM(gst_amount) as gst_collected'),
                    DB::raw('SUM(total_amount) as gross_total'),
                    DB::raw('COUNT(*) as order_count')
                )
                ->groupBy(DB::raw('MONTH(created_at)'))
                ->orderBy('month')
                ->get()
                ->keyBy('month');

            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $row = $monthly->get($m);
                $months[] = [
                    'month'          => $m,
                    'month_name'     => date('F', mktime(0, 0, 0, $m, 1)),
                    'order_count'    => $row?->order_count ?? 0,
                    'taxable_value'  => round($row?->taxable_value ?? 0, 2),
                    'gst_collected'  => round($row?->gst_collected ?? 0, 2),
                    'gross_total'    => round($row?->gross_total ?? 0, 2),
                ];
            }

            return [
                'year'            => $year,
                'annual_gst'      => round($monthly->sum('gst_collected'), 2),
                'annual_revenue'  => round($monthly->sum('gross_total'), 2),
                'monthly'         => $months,
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

    public function gstExport(Request $request)
    {
        $request->validate(['year' => ['required', 'integer', 'min:2020']]);

        $filename = 'gst_summary_' . $request->year . '.xlsx';
        return Excel::download(new GstExport($request->year), $filename);
    }
}
