<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $fillable = [
        'title', 'code', 'description',
        'discount_type', 'discount_value',
        'min_order_amount', 'max_discount_amount',
        'applicable_to', 'applicable_grade', 'customer_type',
        'valid_from', 'valid_to',
        'max_usage', 'usage_count',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'valid_from'   => 'datetime',
        'valid_to'     => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if (now()->lt($this->valid_from) || now()->gt($this->valid_to)) return false;
        if ($this->max_usage && $this->usage_count >= $this->max_usage) return false;
        return true;
    }

    public function calculateDiscount(float $orderTotal): float
    {
        if ($orderTotal < $this->min_order_amount) return 0;

        $discount = $this->discount_type === 'percentage'
            ? ($orderTotal * $this->discount_value / 100)
            : $this->discount_value;

        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round(min($discount, $orderTotal), 2);
    }
}
