<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $table = 'attendance';

    protected $fillable = [
        'employee_id', 'date', 'status', 'check_in', 'check_out',
        'check_in_selfie', 'check_in_lat', 'check_in_lng',
        'check_out_selfie', 'check_out_lat', 'check_out_lng',
    ];

    protected $casts = [
        'date'             => 'date',
        'check_in'         => 'datetime',
        'check_out'        => 'datetime',
        'check_in_lat'     => 'decimal:7',
        'check_in_lng'     => 'decimal:7',
        'check_out_lat'    => 'decimal:7',
        'check_out_lng'    => 'decimal:7',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
