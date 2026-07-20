<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner Web Portal — VIEW-ONLY data.
 * Every query is scoped to the logged-in partner's dealer_id, so a partner
 * can only ever see their own orders, invoices and dues.
 * Ordering / payment stay in the mobile app — not exposed here.
 */
class PartnerPortalController extends Controller
{
    use ApiResponse;

    /** Resolve the dealer that belongs to the logged-in partner (or 403). */
    private function dealer(Request $request): ?Dealer
    {
        return $request->user()->loadMissing('dealer')->dealer;
    }

    /** Dashboard summary tiles. */
    public function dashboard(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);
        if (!$dealer) {
            return $this->error('No business partner profile linked to this account.', 404);
        }

        $orders = Order::where('dealer_id', $dealer->id);

        $creditLimit = (float) $dealer->credit_limit;
        $creditUsed  = (float) $dealer->credit_used;

        return $this->success([
            'business_name'      => $dealer->business_name,
            'kyc_status'         => $dealer->kyc_status,
            'total_orders'       => (clone $orders)->count(),
            'active_orders'      => (clone $orders)->whereIn('status', ['pending', 'approved', 'picking', 'packing', 'packed', 'dispatched'])->count(),
            'delivered_orders'   => (clone $orders)->where('status', 'delivered')->count(),
            'lifetime_purchases' => (float) (clone $orders)->where('status', 'delivered')->sum('total_amount'),
            'credit_limit'       => $creditLimit,
            'credit_used'        => $creditUsed,
            'available_credit'   => max(0, $creditLimit - $creditUsed),
            'recent_orders'      => (clone $orders)->latest()->limit(5)->get(['id', 'order_number', 'status', 'total_amount', 'created_at']),
        ]);
    }

    /** Paginated list of the partner's own orders. */
    public function orders(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);
        if (!$dealer) {
            return $this->error('No business partner profile linked to this account.', 404);
        }

        $orders = Order::where('dealer_id', $dealer->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->withCount('items')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($orders);
    }

    /** Detail of a single order — guarded to the partner's own dealer_id. */
    public function orderShow(Request $request, Order $order): JsonResponse
    {
        $dealer = $this->dealer($request);
        if (!$dealer || $order->dealer_id !== $dealer->id) {
            return $this->error('Order not found.', 404);
        }

        $order->load(['items.product:id,brand,model,category,grade', 'payments', 'invoice']);
        return $this->success($order);
    }

    /** Paginated list of the partner's own invoices. */
    public function invoices(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);
        if (!$dealer) {
            return $this->error('No business partner profile linked to this account.', 404);
        }

        $invoices = Invoice::where('dealer_id', $dealer->id)
            ->with('order:id,order_number,status')
            ->latest('issued_at')
            ->paginate($request->integer('per_page', 15));

        return $this->paginated($invoices);
    }

    /** Dues / credit ledger summary for the partner. */
    public function dues(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);
        if (!$dealer) {
            return $this->error('No business partner profile linked to this account.', 404);
        }

        $creditLimit = (float) $dealer->credit_limit;
        $creditUsed  = (float) $dealer->credit_used;

        // Unpaid / partially paid orders make up the outstanding picture
        $unpaidOrders = Order::where('dealer_id', $dealer->id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->latest()
            ->get(['id', 'order_number', 'status', 'payment_status', 'total_amount', 'created_at']);

        return $this->success([
            'credit_limit'       => $creditLimit,
            'credit_used'        => $creditUsed,
            'available_credit'   => max(0, $creditLimit - $creditUsed),
            'outstanding_amount' => $creditUsed,
            'unpaid_orders'      => $unpaidOrders,
            'note'               => 'To make a payment, please use the DXEmpire mobile app or contact your sales representative.',
        ]);
    }
}
