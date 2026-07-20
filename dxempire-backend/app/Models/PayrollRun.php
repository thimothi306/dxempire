<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'month', 'year', 'status', 'processed_at', 'total_payout',
    ];

    protected $casts = [
        'total_payout' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
