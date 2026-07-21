<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use App\Models\Dealer;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Partner Web/App Portal.
 * Every query is scoped to the logged-in partner's dealer_id, so a partner
 * can only ever see or act on their own orders, invoices and dues.
 * Order placement (store) is self-service: the partner picks items from
 * /partner/catalog by model+grade+quantity; the dealer_id is always taken
 * from the authenticated partner, never from client input.
 */
class PartnerPortalController extends Controller
{
    use ApiResponse;

    public function __construct(private OrderService $orderService) {}

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

    /**
     * Place a new order from the catalog.
     * Body: { "items": [ { "brand", "model", "grade", "category"?, "quantity" } ], "notes"? }
     * dealer_id is ALWAYS the authenticated partner's own dealer — never client-supplied.
     */
    public function store(Request $request): JsonResponse
    {
        $dealer = $this->dealer($request);
        if (!$dealer) {
            return $this->error('No business partner profile linked to this account.', 404);
        }

        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1', 'max:20'],
            'items.*.brand'      => ['required', 'string'],
            'items.*.model'      => ['required', 'string'],
            'items.*.grade'      => ['required', 'string', 'in:S1,S2,S3,S4,S5'],
            'items.*.category'   => ['nullable', 'string', 'in:phone,laptop,accessory'],
            'items.*.quantity'   => ['required', 'integer', 'min:1', 'max:50'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ]);

        DB::beginTransaction();

        try {
            // Resolve each { brand, model, grade, quantity } line into specific
            // in-stock unit IDs (locked, so two partners can't grab the same unit).
            $productIds = [];
            foreach ($data['items'] as $line) {
                $query = Product::where('status', 'in_stock')
                    ->where('brand', $line['brand'])
                    ->where('model', $line['model'])
                    ->where('grade', $line['grade'])
                    ->when($line['category'] ?? null, fn($q) => $q->where('category', $line['category']))
                    ->lockForUpdate();

                $available = $query->limit($line['quantity'])->pluck('id');

                if ($available->count() < $line['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Only {$available->count()} unit(s) available for {$line['brand']} {$line['model']} (Grade {$line['grade']}), requested {$line['quantity']}.",
                    ]);
                }

                array_push($productIds, ...$available->all());
            }

            $products = $this->orderService->validateAndLockStock($productIds);
            $totals   = $this->orderService->calculateTotals($products, $dealer);

            if (!$dealer->canPlaceOrder($totals['total'])) {
                DB::rollBack();
                return $this->error(
                    'Insufficient credit or KYC not verified. Available: ₹' . number_format($dealer->availableCredit(), 2),
                    422
                );
            }

            $order = Order::create([
                'order_number'   => $this->orderService->generateOrderNumber(),
                'dealer_id'      => $dealer->id,
                'status'         => 'pending',
                'payment_status' => 'unpaid',
                'subtotal'       => $totals['subtotal'],
                'gst_amount'     => $totals['gst_amount'],
                'total_amount'   => $totals['total'],
                'credit_used'    => $totals['total'],
                'billing_state'  => $dealer->state,
                'shipping_state' => $dealer->state,
                'notes'          => $data['notes'] ?? null,
            ]);

            foreach ($totals['items'] as $item) {
                $order->items()->create($item);
            }

            Product::whereIn('id', $productIds)->update(['status' => 'reserved']);

            AuditLog::record(
                $request->user()->id,
                'partner_order.created',
                Order::class,
                $order->id,
                [],
                ['order_number' => $order->order_number, 'total' => $totals['total']]
            );

            DB::commit();

            $order->load(['items.product:id,brand,model,category,grade']);
            return $this->success($order, 'Order placed successfully.', 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Could not place order: ' . $e->getMessage(), 422);
        }
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
