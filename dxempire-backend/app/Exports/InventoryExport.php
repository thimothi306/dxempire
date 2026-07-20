<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class InventoryExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(private Request $request) {}

    public function query()
    {
        return Product::with(['supplier', 'bin'])
            ->scopeFilter($this->request)
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'ID', 'IMEI', 'Serial Number', 'Category', 'Brand', 'Model',
            'Grade', 'Status', 'Bin', 'Purchase Price', 'Selling Price',
            'Supplier', 'QC Passed At', 'Created At',
        ];
    }

    public function map($product): array
    {
        return [
            $product->id,
            $product->imei ?? '-',
            $product->serial_number ?? '-',
            $product->category,
            $product->brand,
            $product->model,
            $product->grade ?? '-',
            $product->status,
            $product->bin?->code ?? '-',
            $product->purchase_price,
            $product->selling_price ?? '-',
            $product->supplier?->name ?? '-',
            $product->qc_passed_at?->format('Y-m-d H:i') ?? '-',
            $product->created_at->format('Y-m-d H:i'),
        ];
    }
}
