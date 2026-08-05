<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    protected $fillable = ['code', 'label', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Codes usable in validation rules — active grades only, in display order. */
    public static function activeCodes(): array
    {
        return static::where('is_active', true)->orderBy('sort_order')->pluck('code')->all();
    }
}
