<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'imei', 'serial_number', 'category', 'brand', 'model',
        'grade', 'status', 'bin_id', 'purchase_price', 'selling_price',
        'supplier_id', 'purchase_order_id', 'qc_passed_at', 'sold_at',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price'  => 'decimal:2',
        'qc_passed_at'   => 'datetime',
        'sold_at'        => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(QcRecord::class);
    }

    public function binMovements(): HasMany
    {
        return $this->hasMany(BinMovement::class);
    }

    public function scopeInStock($query)
    {
        return $query->where('status', 'in_stock');
    }

    public function scopeFilter($query, $request)
    {
        return $query
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->grade,    fn($q) => $q->where('grade', $request->grade))
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->bin_id,   fn($q) => $q->where('bin_id', $request->bin_id))
            ->when($request->brand,    fn($q) => $q->where('brand', 'like', '%' . $request->brand . '%'))
            ->when($request->search,   fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('imei', 'like', '%' . $request->search . '%')
                   ->orWhere('serial_number', 'like', '%' . $request->search . '%')
                   ->orWhere('model', 'like', '%' . $request->search . '%');
            }));
    }
}
