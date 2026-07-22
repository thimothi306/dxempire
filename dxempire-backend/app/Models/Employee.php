<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'phone', 'email', 'employee_code',
        'department', 'designation', 'employment_type', 'shift',
        'basic_salary', 'join_date', 'is_active',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'join_date'    => 'date',
        'is_active'    => 'boolean',
    ];

    // Frontend-facing aliases for the DB column names used elsewhere (Payroll/Attendance).
    protected $appends = ['salary', 'joining_date'];

    public function getSalaryAttribute(): float
    {
        return (float) $this->basic_salary;
    }

    public function getJoiningDateAttribute(): ?string
    {
        return $this->join_date?->toDateString();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public static function generateEmployeeCode(): string
    {
        $last = self::withTrashed()->orderByDesc('id')->first();
        $next = $last ? $last->id + 1 : 1;

        return 'EMP' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
