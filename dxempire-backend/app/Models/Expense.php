<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'category', 'amount', 'vendor', 'description',
        'receipt_path', 'incurred_at', 'created_by',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'incurred_at' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
