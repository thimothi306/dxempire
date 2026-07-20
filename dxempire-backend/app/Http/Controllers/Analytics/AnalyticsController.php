<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\BinMovement;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\QcRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function dashboard(): JsonResponse
    {
        $data = Cache::remember('analytics:dashboard', 300, function () {
            return [
                'today_revenue'      => Order::whereDate('created_at', today())
                                            ->where('status', 'delivered')
                                            ->sum('total_amount'),
                'week_revenue'       => Order::whereBetween('created_at', [now()->startOfWeek(), now()])
                                            ->where('status', 'delivered')
                                            ->sum('total_amount'),
                'month_revenue'      => Order::whereMonth('created_at', now()->month)
                                            ->whereYear('created_at', now()->year)
                                            ->where('status', 'delivered')
                                            ->sum('total_amount'),
                'active_orders'      => Order::whereIn('status', ['approved', 'picking', 'packing', 'dispatched'])->count(),
                'pending_qc'         => Product::where('status', 'received')->count(),
                'pending_dispatch'   => Order::where('status', 'packing')->count(),
                'in_refurbishment'   => Product::where('status', 'refurbishment')->count(),
                'total_in_stock'     => Product::where('status', 'in_stock')->count(),
            ];
        });

        return $this->success($data);
    }

    public function revenue(Request $request): JsonResponse
    {
        $request->validate([
            'period'  => ['nullable', 'in:daily,weekly,monthly'],
            'from'    => ['nullable', 'date'],
            'to'      => ['nullable', 'date'],
            'channel' => ['nullable', 'in:b2b,retail'],
        ]);

        $from    = $request->from    ?? now()->subDays(30)->toDateString();
        $to      = $request->to      ?? now()->toDateString();
        $period  = $request->period  ?? 'daily';

        $groupFormat = match ($period) {
            'weekly'  => 'YEARWEEK(created_at)',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default   => 'DATE(created_at)',
        };

        $labelFormat = match ($period) {
            'weekly'  => 'YEARWEEK(created_at)',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default   => 'DATE(created_at)',
        };

        $timeSeries = DB::select("
            SELECT
                {$labelFormat} as period,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value
            FROM orders
            WHERE status = 'delivered'
              AND DATE(created_at) BETWEEN ? AND ?
              " . ($request->channel === 'b2b' ? "AND dealer_id IS NOT NULL" : ($request->channel === 'retail' ? "AND dealer_id IS NULL" : "")) . "
            GROUP BY {$groupFormat}
            ORDER BY period ASC
        ", [$from, $to]);

        $topProducts = DB::select("
            SELECT p.brand, p.model, p.category, p.grade,
                   COUNT(oi.id) as units_sold,
                   SUM(oi.line_total) as revenue
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            JOIN orders o ON o.id = oi.order_id
            WHERE o.status = 'delivered'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY p.brand, p.model, p.category, p.grade
            ORDER BY revenue DESC
            LIMIT 10
        ", [$from, $to]);

        $topDealers = DB::select("
            SELECT d.business_name, d.gst_number,
                   COUNT(o.id) as order_count,
                   SUM(o.total_amount) as revenue
            FROM orders o
            JOIN dealers d ON d.id = o.dealer_id
            WHERE o.status = 'delivered'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY d.id, d.business_name, d.gst_number
            ORDER BY revenue DESC
            LIMIT 10
        ", [$from, $to]);

        $summary = DB::selectOne("
            SELECT
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value
            FROM orders
            WHERE status = 'delivered'
              AND DATE(created_at) BETWEEN ? AND ?
        ", [$from, $to]);

        return $this->success([
            'period'       => ['from' => $from, 'to' => $to, 'group_by' => $period],
            'summary'      => $summary,
            'time_series'  => $timeSeries,
            'top_products' => $topProducts,
            'top_dealers'  => $topDealers,
        ]);
    }

    /**
     * Sales breakdown by category, brand, or grade for a date range.
     */
    public function sales(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
            'group_by' => ['nullable', 'in:category,brand,grade'],
        ]);

        $from    = $request->input('from', now()->subDays(30)->toDateString());
        $to      = $request->input('to', now()->toDateString());
        $groupBy = $request->input('group_by', 'category');
        $key     = "analytics:sales:{$from}:{$to}:{$groupBy}";

        $data = Cache::remember($key, 300, function () use ($from, $to, $groupBy) {
            $breakdown = DB::select("
                SELECT p.{$groupBy} as segment,
                       COUNT(oi.id) as units_sold,
                       SUM(oi.line_total) as revenue,
                       SUM(oi.gst_amount) as gst_collected,
                       AVG(oi.unit_price) as avg_unit_price
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                JOIN orders o ON o.id = oi.order_id
                WHERE o.status IN ('delivered','dispatched')
                  AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY p.{$groupBy}
                ORDER BY revenue DESC
            ", [$from, $to]);

            // Channel split: B2B (dealer) vs retail
            $channelSplit = DB::selectOne("
                SELECT
                    SUM(CASE WHEN dealer_id IS NOT NULL THEN total_amount ELSE 0 END) as b2b_revenue,
                    SUM(CASE WHEN dealer_id IS NULL THEN total_amount ELSE 0 END) as retail_revenue,
                    COUNT(CASE WHEN dealer_id IS NOT NULL THEN 1 END) as b2b_orders,
                    COUNT(CASE WHEN dealer_id IS NULL THEN 1 END) as retail_orders
                FROM orders
                WHERE status IN ('delivered','dispatched')
                  AND DATE(created_at) BETWEEN ? AND ?
            ", [$from, $to]);

            return [
                'period'        => ['from' => $from, 'to' => $to, 'group_by' => $groupBy],
                'breakdown'     => $breakdown,
                'channel_split' => $channelSplit,
            ];
        });

        return $this->success($data);
    }

    /**
     * Inventory health: stock levels, aging buckets, slow movers.
     */
    public function inventory(Request $request): JsonResponse
    {
        $key = 'analytics:inventory:' . now()->format('Y-m-d-H');

        $data = Cache::remember($key, 300, function () {
            // Stock by category and grade
            $stockMatrix = DB::select("
                SELECT category, grade, COUNT(*) as count
                FROM products
                WHERE status = 'in_stock'
                  AND deleted_at IS NULL
                GROUP BY category, grade
                ORDER BY category, grade
            ");

            // Aging buckets: days since received_at (using created_at as proxy)
            $aging = DB::select("
                SELECT
                    CASE
                        WHEN DATEDIFF(NOW(), created_at) <= 30  THEN '0-30 days'
                        WHEN DATEDIFF(NOW(), created_at) <= 60  THEN '31-60 days'
                        WHEN DATEDIFF(NOW(), created_at) <= 90  THEN '61-90 days'
                        ELSE '90+ days'
                    END as age_bucket,
                    COUNT(*) as count,
                    SUM(selling_price) as stock_value
                FROM products
                WHERE status = 'in_stock'
                  AND deleted_at IS NULL
                GROUP BY age_bucket
                ORDER BY MIN(DATEDIFF(NOW(), created_at))
            ");

            // Slow movers: in_stock for 60+ days
            $slowMovers = Product::where('status', 'in_stock')
                ->where('created_at', '<=', now()->subDays(60))
                ->select('id', 'brand', 'model', 'category', 'grade', 'selling_price', 'created_at')
                ->orderBy('created_at')
                ->limit(20)
                ->get()
                ->map(fn($p) => array_merge($p->toArray(), [
                    'days_in_stock' => (int) now()->diffInDays($p->created_at),
                ]));

            // Total stock value
            $stockValue = Product::where('status', 'in_stock')
                ->whereNull('deleted_at')
                ->sum('selling_price');

            $pendingQc   = Product::where('status', 'received')->count();
            $refurb      = Product::where('status', 'refurbishment')->count();
            $totalInStock = Product::where('status', 'in_stock')->count();

            return [
                'summary' => [
                    'total_in_stock'    => $totalInStock,
                    'total_stock_value' => round($stockValue, 2),
                    'pending_qc'        => $pendingQc,
                    'in_refurbishment'  => $refurb,
                ],
                'stock_matrix' => $stockMatrix,
                'aging_buckets' => $aging,
                'slow_movers'  => $slowMovers,
            ];
        });

        return $this->success($data);
    }

    /**
     * Bin/stock movement history with optional filters.
     */
    public function stockMovements(Request $request): JsonResponse
    {
        $request->validate([
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'bin_id'     => ['nullable', 'integer', 'exists:bins,id'],
        ]);

        $movements = BinMovement::with([
                'product:id,brand,model,imei',
                'fromBin:id,code',
                'toBin:id,code',
                'mover:id,name',
            ])
            ->when($request->input('from'), fn($q) => $q->whereDate('moved_at', '>=', $request->input('from')))
            ->when($request->input('to'), fn($q) => $q->whereDate('moved_at', '<=', $request->input('to')))
            ->when($request->input('product_id'), fn($q) => $q->where('product_id', $request->input('product_id')))
            ->when($request->input('bin_id'), fn($q) =>
                $q->where('from_bin_id', $request->input('bin_id'))
                  ->orWhere('to_bin_id', $request->input('bin_id'))
            )
            ->orderByDesc('moved_at')
            ->paginate($request->integer('per_page', 30));

        return $this->paginated($movements);
    }

    /**
     * Dealer/partner performance ranking for a date range.
     */
    public function partnerPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
        ]);

        $from = $request->input('from', now()->subDays(90)->toDateString());
        $to   = $request->input('to', now()->toDateString());
        $key  = "analytics:partners:{$from}:{$to}";

        $data = Cache::remember($key, 300, function () use ($from, $to) {
            $dealers = DB::select("
                SELECT
                    d.id,
                    d.business_name,
                    d.price_tier,
                    d.credit_limit,
                    d.credit_used,
                    COUNT(o.id) as order_count,
                    SUM(o.total_amount) as total_revenue,
                    AVG(o.total_amount) as avg_order_value,
                    SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as amount_paid,
                    SUM(CASE WHEN o.payment_status != 'paid' THEN o.total_amount ELSE 0 END) as amount_outstanding,
                    MAX(o.created_at) as last_order_at
                FROM dealers d
                LEFT JOIN orders o
                    ON o.dealer_id = d.id
                    AND o.status IN ('delivered','dispatched','approved')
                    AND DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY d.id, d.business_name, d.price_tier, d.credit_limit, d.credit_used
                ORDER BY total_revenue DESC
            ", [$from, $to]);

            return [
                'period'  => ['from' => $from, 'to' => $to],
                'dealers' => array_map(fn($d) => array_merge((array) $d, [
                    'payment_rate_pct' => $d->total_revenue > 0
                        ? round(($d->amount_paid / $d->total_revenue) * 100, 1)
                        : 0,
                    'credit_utilisation_pct' => $d->credit_limit > 0
                        ? round(($d->credit_used / $d->credit_limit) * 100, 1)
                        : 0,
                ]), $dealers),
            ];
        });

        return $this->success($data);
    }

    /**
     * Simple demand forecast: 3-month rolling average per category,
     * projected for next month.
     */
    public function forecast(): JsonResponse
    {
        $key = 'analytics:forecast:' . now()->format('Y-m');

        $data = Cache::remember($key, 3600, function () {
            // Units sold per category per month for last 6 months
            $history = DB::select("
                SELECT
                    p.category,
                    DATE_FORMAT(o.created_at, '%Y-%m') as month,
                    COUNT(oi.id) as units_sold
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                JOIN orders o ON o.id = oi.order_id
                WHERE o.status IN ('delivered','dispatched')
                  AND o.created_at >= ?
                GROUP BY p.category, DATE_FORMAT(o.created_at, '%Y-%m')
                ORDER BY p.category, month
            ", [now()->subMonths(6)->startOfMonth()]);

            // Build per-category series and compute 3-month moving average
            $byCategory = collect($history)->groupBy('category');
            $nextMonth  = now()->addMonth()->format('Y-m');

            $forecasts = $byCategory->map(function ($rows, $category) use ($nextMonth) {
                $monthly  = $rows->pluck('units_sold', 'month')->toArray();
                $last3    = array_slice(array_values($monthly), -3);
                $forecast = count($last3) > 0 ? round(array_sum($last3) / count($last3)) : 0;

                return [
                    'category'       => $category,
                    'history'        => $monthly,
                    'forecast_month' => $nextMonth,
                    'forecast_units' => $forecast,
                    'current_stock'  => Product::where('category', $category)
                                               ->where('status', 'in_stock')
                                               ->count(),
                ];
            })->values();

            return [
                'generated_at'  => now()->toDateTimeString(),
                'forecast_month'=> $nextMonth,
                'method'        => '3-month rolling average',
                'categories'    => $forecasts,
            ];
        });

        return $this->success($data);
    }
}
