<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiSummaryController extends Controller
{
    use ApiResponse;

    public function insights(GeminiService $gemini): JsonResponse
    {
        $cacheKey = 'ai:insights:' . now()->toDateString();
        $summary  = Cache::get($cacheKey);

        if (!$summary) {
            $stats = $this->gatherInsightStats();
            $text  = $gemini->generate($this->buildInsightsPrompt($stats));

            $summary = [
                'text'         => $text,
                'stats'        => $stats,
                'generated_at' => now()->toIso8601String(),
            ];

            // Only cache a successful generation — memoizing a transient
            // Gemini failure would otherwise hide the insight card for the
            // rest of the day even once Gemini recovers.
            if ($text !== null) {
                Cache::put($cacheKey, $summary, now()->endOfDay());
            }
        }

        return $this->success($summary);
    }

    private function gatherInsightStats(): array
    {
        $currentFrom = now()->subDays(30)->toDateString();
        $currentTo   = now()->toDateString();
        $priorFrom   = now()->subDays(60)->toDateString();
        $priorTo     = now()->subDays(31)->toDateString();

        $current = DB::selectOne("
            SELECT COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue
            FROM orders
            WHERE status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?
        ", [$currentFrom, $currentTo]);

        $prior = DB::selectOne("
            SELECT COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue
            FROM orders
            WHERE status = 'delivered' AND DATE(created_at) BETWEEN ? AND ?
        ", [$priorFrom, $priorTo]);

        $revenueChangePct = $prior->revenue > 0
            ? round((($current->revenue - $prior->revenue) / $prior->revenue) * 100, 1)
            : null;

        $topProduct = DB::selectOne("
            SELECT p.brand, p.model, SUM(oi.line_total) as revenue
            FROM order_items oi
            JOIN products p ON p.id = oi.product_id
            JOIN orders o ON o.id = oi.order_id
            WHERE o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY p.brand, p.model
            ORDER BY revenue DESC
            LIMIT 1
        ", [$currentFrom, $currentTo]);

        $topDealer = DB::selectOne("
            SELECT d.business_name, SUM(o.total_amount) as revenue
            FROM orders o
            JOIN dealers d ON d.id = o.dealer_id
            WHERE o.status = 'delivered' AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY d.id, d.business_name
            ORDER BY revenue DESC
            LIMIT 1
        ", [$currentFrom, $currentTo]);

        $slowMovers = Product::where('status', 'in_stock')
            ->where('created_at', '<=', now()->subDays(60))
            ->count();

        $stockValue = Product::where('status', 'in_stock')->whereNull('deleted_at')->sum('selling_price');

        return [
            'last_30d_revenue'    => (float) $current->revenue,
            'prior_30d_revenue'   => (float) $prior->revenue,
            'revenue_change_pct'  => $revenueChangePct,
            'last_30d_orders'     => (int) $current->orders,
            'prior_30d_orders'    => (int) $prior->orders,
            'top_product'         => $topProduct ? "{$topProduct->brand} {$topProduct->model}" : null,
            'top_product_revenue' => $topProduct ? (float) $topProduct->revenue : null,
            'top_dealer'          => $topDealer->business_name ?? null,
            'top_dealer_revenue'  => $topDealer ? (float) $topDealer->revenue : null,
            'slow_movers_count'   => $slowMovers,
            'stock_value'         => round((float) $stockValue, 2),
        ];
    }

    private function buildInsightsPrompt(array $s): string
    {
        $revenueChange = $s['revenue_change_pct'] === null
            ? 'not comparable (no revenue in the prior 30-day period)'
            : "{$s['revenue_change_pct']}%";

        return <<<PROMPT
            You are writing a brief analytics insight for the admin of DXEMPIRE, a refurbished phone and laptop reseller. Use ONLY the numbers given below — never invent or estimate any figure, cause, or location not listed here.

            Last 30 days vs prior 30 days:
            - Revenue last 30 days: ₹{$s['last_30d_revenue']}
            - Revenue prior 30 days: ₹{$s['prior_30d_revenue']}
            - Revenue change: {$revenueChange}
            - Orders last 30 days: {$s['last_30d_orders']}
            - Orders prior 30 days: {$s['prior_30d_orders']}
            - Best-selling product (last 30 days): {$s['top_product']} (₹{$s['top_product_revenue']} revenue)
            - Top dealer (last 30 days): {$s['top_dealer']} (₹{$s['top_dealer_revenue']} revenue)
            - Products sitting in stock 60+ days (slow movers): {$s['slow_movers_count']}
            - Total current stock value: ₹{$s['stock_value']}

            Write a plain-text insight, 3-4 sentences, business-like. State whether revenue is trending up or down and by how much, mention the best-selling product or top dealer if notable, and flag the slow-mover count only if it seems worth attention. No markdown, no bullet points, no headings. Do not speculate about causes (region, marketing, etc.) beyond what these numbers show.
            PROMPT;
    }

    public function daily(GeminiService $gemini): JsonResponse
    {
        $cacheKey = 'ai:daily-summary:' . now()->toDateString();
        $summary  = Cache::get($cacheKey);

        if (!$summary) {
            $stats = $this->gatherStats();
            $text  = $gemini->generate($this->buildPrompt($stats));

            $summary = [
                'text'         => $text,
                'stats'        => $stats,
                'generated_at' => now()->toIso8601String(),
            ];

            if ($text !== null) {
                Cache::put($cacheKey, $summary, now()->endOfDay());
            }
        }

        return $this->success($summary);
    }

    /**
     * Real, already-computed numbers only — the AI narrates these, it never
     * invents or calculates figures itself.
     */
    private function gatherStats(): array
    {
        $todayRevenue = Order::whereDate('created_at', today())
            ->where('status', 'delivered')
            ->sum('total_amount');

        $activeOrders    = Order::whereIn('status', ['approved', 'picking', 'packing', 'dispatched'])->count();
        $pendingQc       = Product::where('status', 'received')->count();
        $pendingDispatch = Order::where('status', 'packing')->count();
        $inRefurbishment = Product::where('status', 'refurbishment')->count();
        $totalInStock    = Product::where('status', 'in_stock')->count();

        $thresholds = Setting::getJson('low_stock_threshold', ['phone' => 10, 'laptop' => 5]);
        $lowStockCount = Product::inStock()
            ->selectRaw('category, grade, count(*) as count')
            ->groupBy('category', 'grade')
            ->get()
            ->filter(function ($row) use ($thresholds) {
                $threshold = $thresholds[$row->category] ?? null;
                return $threshold !== null && $row->grade && $row->count < $threshold;
            })
            ->count();

        return [
            'today_revenue'      => (float) $todayRevenue,
            'active_orders'      => $activeOrders,
            'pending_qc'         => $pendingQc,
            'pending_dispatch'   => $pendingDispatch,
            'in_refurbishment'   => $inRefurbishment,
            'total_in_stock'     => $totalInStock,
            'low_stock_grades'   => $lowStockCount,
        ];
    }

    private function buildPrompt(array $s): string
    {
        return <<<PROMPT
            You are writing a short morning briefing for the admin of DXEMPIRE, a refurbished phone and laptop reseller. Use ONLY the numbers given below — never invent or estimate any figure not listed.

            Today's numbers:
            - Revenue today (delivered orders): ₹{$s['today_revenue']}
            - Active orders in progress: {$s['active_orders']}
            - Products waiting for QC: {$s['pending_qc']}
            - Orders waiting to be dispatched: {$s['pending_dispatch']}
            - Products currently in refurbishment: {$s['in_refurbishment']}
            - Total units in stock: {$s['total_in_stock']}
            - Grade/category combinations currently below the low-stock threshold: {$s['low_stock_grades']}

            Write a plain-text summary, 2-4 sentences, friendly but business-like. No markdown, no bullet points, no headings. Call out anything that needs attention (pending QC backlog, low stock, pending dispatch) before the good news. If everything is fine, say so briefly.
            PROMPT;
    }
}
