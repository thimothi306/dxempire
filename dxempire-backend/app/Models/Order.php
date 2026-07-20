<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number', 'dealer_id', 'customer_id', 'retail_customer_id', 'order_channel',
        'status', 'payment_status',
        'subtotal', 'gst_amount', 'total_amount', 'credit_used',
        'billing_state', 'shipping_state',
        'awb_number', 'logistics_provider', 'dispatched_at', 'delivered_at', 'notes',
    ];

    protected $casts = [
        'subtotal'      => 'decimal:2',
        'gst_amount'    => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'credit_used'   => 'decimal:2',
        'dispatched_at' => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    public function dealer(): BelongsTo
    {
        return $this->belongsTo(Dealer::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    public function retailCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'retail_customer_id');
    }
}
