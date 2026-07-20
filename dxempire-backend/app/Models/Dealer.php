<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    protected $fillable = [
        'user_id', 'business_name', 'gst_number', 'kyc_status',
        'credit_limit', 'credit_used', 'price_tier', 'state', 'pincode',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_used'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function availableCredit(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->credit_used);
    }

    public function canPlaceOrder(float $amount): bool
    {
        return $this->kyc_status === 'verified'
            && ($this->credit_used + $amount) <= $this->credit_limit;
    }
}
