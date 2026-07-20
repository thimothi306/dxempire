<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class GstExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private int $year) {}

    public function collection()
    {
        return collect(DB::select("
            SELECT
                MONTH(created_at) as month_num,
                DATE_FORMAT(created_at, '%M %Y') as month,
                COUNT(*) as order_count,
                SUM(subtotal) as taxable_value,
                SUM(gst_amount) as gst_collected,
                SUM(total_amount) as gross_total
            FROM orders
            WHERE status IN ('delivered','dispatched','approved')
              AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at), DATE_FORMAT(created_at, '%M %Y')
            ORDER BY month_num
        ", [$this->year]))->map(fn($r) => [
            $r->month,
            $r->order_count,
            round($r->taxable_value, 2),
            round($r->gst_collected, 2),
            round($r->gross_total, 2),
        ]);
    }

    public function headings(): array
    {
        return ['Month', 'Orders', 'Taxable Value (₹)', 'GST Collected (₹)', 'Gross Total (₹)'];
    }

    public function title(): string
    {
        return 'GST Summary ' . $this->year;
    }
}
