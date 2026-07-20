<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bin extends Model
{
    protected $fillable = ['code', 'zone', 'row', 'shelf', 'capacity', 'current_count'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BinMovement::class, 'to_bin_id');
    }

    public function hasCapacity(): bool
    {
        return $this->current_count < $this->capacity;
    }
}
