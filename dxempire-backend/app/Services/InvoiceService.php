<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate (or retrieve existing) GST invoice for an order.
     */
    public function generate(Order $order): Invoice
    {
        $existing = $order->invoice;
        if ($existing) {
            return $existing;
        }

        $order->loadMissing(['items.product', 'dealer.user']);

        $invoiceNumber = $this->generateInvoiceNumber();
        $company       = $this->companyDetails();

        $pdf = Pdf::loadView('invoices.gst_invoice', [
            'order'         => $order,
            'invoice_number'=> $invoiceNumber,
            'company'       => $company,
            'issued_at'     => now(),
        ])->setPaper('a4', 'portrait');

        $relativePath = "invoices/{$invoiceNumber}.pdf";
        Storage::put($relativePath, $pdf->output());

        $buyerState = $order->billing_state ?? $order->dealer?->state ?? $order->retailCustomer?->state;
        $gstSplit   = app(OrderService::class)->calculateGstSplit((float) $order->gst_amount, $buyerState);

        return Invoice::create([
            'order_id'       => $order->id,
            'invoice_number' => $invoiceNumber,
            'dealer_id'      => $order->dealer_id,
            'subtotal'       => $order->subtotal,
            'gst_amount'     => $order->gst_amount,
            'cgst_amount'    => $gstSplit['cgst_amount'],
            'sgst_amount'    => $gstSplit['sgst_amount'],
            'igst_amount'    => $gstSplit['igst_amount'],
            'tax_type'       => $gstSplit['tax_type'],
            'billing_state'  => $buyerState,
            'shipping_state' => $order->shipping_state ?? $buyerState,
            'total'          => $order->total_amount,
            'pdf_path'       => $relativePath,
            'issued_at'      => now(),
        ]);
    }

    private function generateInvoiceNumber(): string
    {
        $year = now()->year;
        $max  = Invoice::whereYear('issued_at', $year)->max('id') ?? 0;

        return 'INV-' . $year . '-' . str_pad($max + 1, 5, '0', STR_PAD_LEFT);
    }

    private function companyDetails(): array
    {
        return [
            'name'       => Setting::get('company_name', 'DXEMPIRE'),
            'address'    => Setting::get('company_address', ''),
            'gst_number' => Setting::get('company_gst', ''),
            'phone'      => Setting::get('company_phone', ''),
            'email'      => Setting::get('company_email', ''),
        ];
    }
}
