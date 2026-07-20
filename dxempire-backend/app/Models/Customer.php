<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'name', 'phone', 'email', 'address', 'city', 'state', 'pincode', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'retail_customer_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(RetailCartItem::class);
    }
}
