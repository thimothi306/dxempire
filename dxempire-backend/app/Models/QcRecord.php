<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcRecord extends Model
{
    protected $fillable = [
        'product_id', 'engineer_id', 'grade',
        'condition_notes', 'outcome', 'graded_at',
    ];

    protected $casts = ['graded_at' => 'datetime'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_id');
    }
}
