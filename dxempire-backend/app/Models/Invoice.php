<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'order_id', 'invoice_number', 'dealer_id',
        'billing_state', 'shipping_state', 'tax_type',
        'subtotal', 'gst_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
        'total', 'pdf_path', 'issued_at',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'gst_amount'   => 'decimal:2',
        'cgst_amount'  => 'decimal:2',
        'sgst_amount'  => 'decimal:2',
        'igst_amount'  => 'decimal:2',
        'total'        => 'decimal:2',
        'issued_at'    => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }
}
