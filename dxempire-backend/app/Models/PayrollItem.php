<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id', 'employee_id', 'days_worked',
        'basic', 'deductions', 'net_salary', 'slip_path',
    ];

    protected $casts = [
        'days_worked' => 'decimal:1',
        'basic'       => 'decimal:2',
        'deductions'  => 'decimal:2',
        'net_salary'  => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
