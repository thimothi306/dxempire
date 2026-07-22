<?php

namespace App\Exports;

use App\Models\Invoice;
use App\Services\OrderService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class GstExport implements FromCollection, WithHeadings, WithTitle
{
    private string $year;
    private string $month;

    /** @param string $ym "YYYY-MM" */
    public function __construct(private string $ym)
    {
        [$this->year, $this->month] = explode('-', $ym);
    }

    public function collection()
    {
        $orderService = app(OrderService::class);

        return Invoice::whereYear('issued_at', $this->year)
            ->whereMonth('issued_at', $this->month)
            ->with(['dealer:id,business_name,gst_number', 'order:id,billing_state'])
            ->orderBy('issued_at')
            ->get()
            ->map(function ($invoice) use ($orderService) {
                $buyerState = $invoice->billing_state ?? $invoice->order?->billing_state ?? $invoice->dealer?->state;
                $split = $orderService->calculateGstSplit((float) $invoice->gst_amount, $buyerState);

                return [
                    $invoice->invoice_number,
                    $invoice->issued_at?->toDateString(),
                    $invoice->dealer?->business_name,
                    $invoice->dealer?->gst_number,
                    round((float) $invoice->subtotal, 2),
                    $split['cgst_amount'],
                    $split['sgst_amount'],
                    $split['igst_amount'],
                    round((float) $invoice->total, 2),
                ];
            });
    }

    public function headings(): array
    {
        return ['Invoice #', 'Date', 'Dealer', 'GSTIN', 'Taxable Value (₹)', 'CGST (₹)', 'SGST (₹)', 'IGST (₹)', 'Total (₹)'];
    }

    public function title(): string
    {
        return 'GST Summary ' . $this->ym;
    }
}
