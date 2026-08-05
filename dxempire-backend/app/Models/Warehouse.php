<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'name', 'code', 'phone', 'email', 'address', 'city', 'state', 'pincode',
        'is_default', 'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }
}
