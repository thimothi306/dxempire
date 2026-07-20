<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PayrollService
{
    /**
     * Process all active employees for a given month/year.
     * Creates PayrollItem per employee, returns total payout.
     */
    public function process(PayrollRun $run): float
    {
        $month = $run->month;
        $year  = $run->year;

        $workingDays = $this->workingDaysInMonth($month, $year);

        $employees = Employee::where('is_active', true)
            ->whereNull('deleted_at')
            ->with(['attendance' => fn($q) => $q
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
            ])
            ->get();

        $totalPayout = 0.0;

        foreach ($employees as $employee) {
            $item = $this->computeItem($run, $employee, $workingDays);
            $totalPayout += $item->net_salary;
        }

        return round($totalPayout, 2);
    }

    private function computeItem(PayrollRun $run, Employee $employee, int $workingDays): PayrollItem
    {
        // Delete any existing draft item for this employee in this run
        PayrollItem::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->delete();

        $daysWorked = $this->calculateDaysWorked($employee->attendance);
        $perDayRate = $workingDays > 0
            ? round((float) $employee->basic_salary / $workingDays, 4)
            : 0;

        $basicEarned = round($perDayRate * $daysWorked, 2);

        // PF: 12% of basic, capped at ₹1,800/month
        $pf = min(round($basicEarned * 0.12, 2), 1800.00);

        // LOP deduction for absent days (beyond what's not present/half)
        $absentDays = $employee->attendance->where('status', 'absent')->count();
        $lop        = round($perDayRate * $absentDays, 2);

        $deductions = round($pf + $lop, 2);
        $netSalary  = max(0, round($basicEarned - $deductions, 2));

        return PayrollItem::create([
            'payroll_run_id' => $run->id,
            'employee_id'    => $employee->id,
            'days_worked'    => $daysWorked,
            'basic'          => $basicEarned,
            'deductions'     => $deductions,
            'net_salary'     => $netSalary,
        ]);
    }

    private function calculateDaysWorked($attendanceCollection): float
    {
        $days = 0.0;
        foreach ($attendanceCollection as $rec) {
            $days += match ($rec->status) {
                'present'  => 1.0,
                'half_day' => 0.5,
                default    => 0.0,
            };
        }
        return $days;
    }

    /**
     * Count calendar weekdays (Mon–Sat) in a given month as working days.
     * Saturday is treated as half-day in many Indian setups; here we count full 6-day weeks.
     */
    private function workingDaysInMonth(int $month, int $year): int
    {
        $days  = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $count = 0;
        for ($d = 1; $d <= $days; $d++) {
            $dow = Carbon::createFromDate($year, $month, $d)->dayOfWeek;
            // 0=Sunday excluded; Mon–Sat counted
            if ($dow !== Carbon::SUNDAY) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Generate and store a PDF pay slip for a single PayrollItem.
     */
    public function generateSlip(PayrollItem $item): string
    {
        $item->loadMissing(['employee.user', 'payrollRun']);
        $run      = $item->payrollRun;
        $employee = $item->employee;

        $monthName   = Carbon::createFromDate($run->year, $run->month, 1)->format('F Y');
        $workingDays = $this->workingDaysInMonth($run->month, $run->year);
        $perDayRate  = $workingDays > 0 ? round((float) $employee->basic_salary / $workingDays, 2) : 0;
        $pf          = min(round((float) $item->basic * 0.12, 2), 1800.00);
        $lop         = round((float) $item->deductions - $pf, 2);

        $pdf = Pdf::loadView('hr.payslip', compact(
            'item', 'employee', 'run', 'monthName', 'workingDays', 'perDayRate', 'pf', 'lop'
        ))->setPaper('a4', 'portrait');

        $path = "payslips/{$run->year}/{$run->month}/slip_{$employee->id}.pdf";
        Storage::put($path, $pdf->output());

        $item->update(['slip_path' => $path]);

        return $path;
    }
}
