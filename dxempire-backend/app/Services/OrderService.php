<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Events\OrderApproved;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function generateOrderNumber(): string
    {
        $year = now()->year;
        $max  = Order::withTrashed()->whereYear('created_at', $year)->max('id') ?? 0;

        return 'DX-' . $year . '-' . str_pad($max + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Validate products are in_stock and lock rows. Used when placing a NEW order.
     */
    public function validateAndLockStock(array $productIds): \Illuminate\Database\Eloquent\Collection
    {
        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        $unavailable = [];
        foreach ($productIds as $id) {
            $product = $products->get($id);
            if (!$product) {
                $unavailable[] = "Product ID {$id} not found.";
            } elseif ($product->status !== 'in_stock') {
                $unavailable[] = "Product ID {$id} ({$product->brand} {$product->model}) is not available. Status: {$product->status}.";
            }
        }

        if (!empty($unavailable)) {
            throw new \RuntimeException(implode(' ', $unavailable));
        }

        return $products;
    }

    /**
     * Validate reserved products still belong to this order (used during approval).
     */
    private function validateReservedStock(array $productIds): void
    {
        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        $invalid = [];
        foreach ($productIds as $id) {
            $product = $products->get($id);
            if (!$product || $product->status !== 'reserved') {
                $invalid[] = "Product ID {$id} is no longer reserved (status: " . ($product?->status ?? 'missing') . ").";
            }
        }

        if (!empty($invalid)) {
            throw new \RuntimeException(implode(' ', $invalid));
        }
    }

    /**
     * Calculate B2B order totals with dealer tier discount and GST.
     */
    public function calculateTotals(
        \Illuminate\Database\Eloquent\Collection $products,
        ?Dealer $dealer
    ): array {
        $discountRate = match ($dealer?->price_tier) {
            'A'     => 0.05,
            'B'     => 0.03,
            default => 0.00,
        };

        $gstRate  = 18.0;
        $subtotal = 0;
        $gstTotal = 0;
        $items    = [];

        foreach ($products as $product) {
            $unitPrice  = round((float) $product->selling_price * (1 - $discountRate), 2);
            $gstAmount  = round($unitPrice * ($gstRate / 100), 2);
            $lineTotal  = round($unitPrice + $gstAmount, 2);

            $subtotal += $unitPrice;
            $gstTotal += $gstAmount;

            $items[] = [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit_price' => $unitPrice,
                'gst_rate'   => $gstRate,
                'gst_amount' => $gstAmount,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'items'      => $items,
            'subtotal'   => round($subtotal, 2),
            'gst_amount' => round($gstTotal, 2),
            'total'      => round($subtotal + $gstTotal, 2),
        ];
    }

    /**
     * Calculate retail (B2C) totals using retail_price.
     */
    public function calculateRetailTotals(\Illuminate\Database\Eloquent\Collection $products): array
    {
        $gstRate  = 18.0;
        $subtotal = 0;
        $gstTotal = 0;
        $items    = [];

        foreach ($products as $product) {
            $unitPrice  = round((float) ($product->retail_price ?? $product->selling_price), 2);
            $gstAmount  = round($unitPrice * ($gstRate / 100), 2);
            $lineTotal  = round($unitPrice + $gstAmount, 2);

            $subtotal += $unitPrice;
            $gstTotal += $gstAmount;

            $items[] = [
                'product_id' => $product->id,
                'quantity'   => 1,
                'unit_price' => $unitPrice,
                'gst_rate'   => $gstRate,
                'gst_amount' => $gstAmount,
                'line_total' => $lineTotal,
            ];
        }

        return [
            'items'      => $items,
            'subtotal'   => round($subtotal, 2),
            'gst_amount' => round($gstTotal, 2),
            'total'      => round($subtotal + $gstTotal, 2),
        ];
    }

    /**
     * CGST+SGST for intra-state; IGST for inter-state.
     * Company state is stored in Settings (key: company_state).
     */
    public function calculateGstSplit(float $gstAmount, ?string $buyerState): array
    {
        $companyState = Setting::get('company_state', 'Odisha');
        $isIntra      = $buyerState && strtolower(trim($buyerState)) === strtolower(trim($companyState));

        if ($isIntra) {
            return [
                'tax_type'    => 'intra',
                'cgst_amount' => round($gstAmount / 2, 2),
                'sgst_amount' => round($gstAmount / 2, 2),
                'igst_amount' => 0.00,
            ];
        }

        return [
            'tax_type'    => 'inter',
            'cgst_amount' => 0.00,
            'sgst_amount' => 0.00,
            'igst_amount' => round($gstAmount, 2),
        ];
    }

    /**
     * Approve order: validate reserved stock, mark sold, reduce dealer credit.
     */
    public function approve(Order $order): void
    {
        DB::beginTransaction();
        try {
            $order->refresh();

            if ($order->status !== 'pending') {
                throw new \RuntimeException("Order {$order->order_number} is already {$order->status}.");
            }

            $productIds = $order->items()->pluck('product_id')->toArray();
            $this->validateReservedStock($productIds);

            Product::whereIn('id', $productIds)->update(['status' => 'sold', 'sold_at' => now()]);
            $order->update(['status' => 'approved']);

            if ($order->dealer_id && $order->credit_used > 0) {
                Dealer::where('id', $order->dealer_id)->increment('credit_used', $order->credit_used);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        event(new OrderApproved($order->fresh()));
    }

    /**
     * Cancel order: release reserved OR sold stock back to in_stock.
     */
    public function cancel(Order $order): void
    {
        if (!in_array($order->status, ['pending', 'approved'])) {
            throw new \RuntimeException("Order cannot be cancelled at status: {$order->status}.");
        }

        DB::beginTransaction();
        try {
            $productIds = $order->items()->pluck('product_id')->toArray();

            // Release both reserved (pending orders) and sold (approved orders)
            Product::whereIn('id', $productIds)->update(['status' => 'in_stock', 'sold_at' => null]);

            if ($order->status === 'approved' && $order->dealer_id && $order->credit_used > 0) {
                Dealer::where('id', $order->dealer_id)->decrement('credit_used', $order->credit_used);
            }

            $order->update(['status' => 'cancelled']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process return: products re-enter QC as qc_pending. return_count incremented.
     */
    public function processReturn(Order $order): void
    {
        if ($order->status !== 'delivered') {
            throw new \RuntimeException("Only delivered orders can be returned.");
        }

        DB::beginTransaction();
        try {
            $productIds = $order->items()->pluck('product_id')->toArray();

            Product::whereIn('id', $productIds)->update([
                'status'       => 'qc_pending',
                'grade'        => null,
                'sold_at'      => null,
                'return_count' => DB::raw('return_count + 1'),
            ]);

            if ($order->dealer_id && $order->credit_used > 0) {
                Dealer::where('id', $order->dealer_id)->decrement('credit_used', $order->credit_used);
            }

            $order->update(['status' => 'returned', 'payment_status' => 'refunded']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
