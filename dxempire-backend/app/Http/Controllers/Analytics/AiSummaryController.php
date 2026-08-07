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

class AiSummaryController extends Controller
{
    use ApiResponse;

    public function daily(GeminiService $gemini): JsonResponse
    {
        $cacheKey = 'ai:daily-summary:' . now()->toDateString();

        $summary = Cache::remember($cacheKey, now()->endOfDay(), function () use ($gemini) {
            $stats = $this->gatherStats();
            $text  = $gemini->generate($this->buildPrompt($stats));

            return [
                'text'         => $text,
                'stats'        => $stats,
                'generated_at' => now()->toIso8601String(),
            ];
        });

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
