<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\MarkAttendanceRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    use ApiResponse;

    /**
     * List attendance records with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $records = Attendance::with('employee.user:id,name')
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->date, fn($q) => $q->whereDate('date', $request->date))
            ->when($request->month && $request->year, fn($q) => $q
                ->whereMonth('date', $request->month)
                ->whereYear('date', $request->year)
            )
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('date')
            ->paginate($request->integer('per_page', 50));

        return $this->paginated($records);
    }

    /**
     * Bulk mark attendance for one or more employees.
     * Upserts by (employee_id, date).
     */
    public function bulkMark(MarkAttendanceRequest $request): JsonResponse
    {
        $saved = [];

        DB::beginTransaction();
        try {
            foreach ($request->records as $rec) {
                $checkIn  = isset($rec['check_in'])
                    ? Carbon::parse($rec['date'] . ' ' . $rec['check_in'])
                    : null;
                $checkOut = isset($rec['check_out'])
                    ? Carbon::parse($rec['date'] . ' ' . $rec['check_out'])
                    : null;

                $saved[] = Attendance::updateOrCreate(
                    ['employee_id' => $rec['employee_id'], 'date' => $rec['date']],
                    ['status' => $rec['status'], 'check_in' => $checkIn, 'check_out' => $checkOut]
                );
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->success($saved, count($saved) . ' attendance record(s) saved.');
    }

    /**
     * Monthly attendance summary for a single employee.
     */
    public function summary(Request $request, Employee $employee): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year'  => ['required', 'integer', 'min:2020'],
        ]);

        $records = Attendance::where('employee_id', $employee->id)
            ->whereMonth('date', $request->month)
            ->whereYear('date', $request->year)
            ->orderBy('date')
            ->get();

        $counts = $records->groupBy('status')->map->count();
        $daysWorked = $records->sum(fn($r) => match ($r->status) {
            'present'  => 1.0,
            'half_day' => 0.5,
            default    => 0.0,
        });

        return $this->success([
            'employee'    => $employee->load('user:id,name'),
            'month'       => (int) $request->month,
            'year'        => (int) $request->year,
            'days_worked' => $daysWorked,
            'present'     => $counts->get('present', 0),
            'half_day'    => $counts->get('half_day', 0),
            'absent'      => $counts->get('absent', 0),
            'leave'       => $counts->get('leave', 0),
            'records'     => $records,
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $record = Attendance::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => now()->toDateString()],
            ['status' => 'present', 'check_in' => now()]
        );

        return $this->success($record, 'Checked in.');
    }

    public function checkOut(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
        ]);

        $record = Attendance::where('employee_id', $request->employee_id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if (!$record) {
            return $this->error('No check-in found for today.', 422);
        }

        $record->update(['check_out' => now()]);

        return $this->success($record->fresh(), 'Checked out.');
    }

    /**
     * Today's attendance status for all active employees.
     */
    public function today(): JsonResponse
    {
        $today     = now()->toDateString();
        $employees = Employee::where('is_active', true)
            ->with(['user:id,name', 'attendance' => fn($q) => $q->whereDate('date', $today)])
            ->get();

        $result = $employees->map(fn($emp) => [
            'employee_id'  => $emp->id,
            'name'         => $emp->user?->name,
            'department'   => $emp->department,
            'shift'        => $emp->shift,
            'attendance'   => $emp->attendance->first()?->only(['status', 'check_in', 'check_out']) ?? null,
        ]);

        $markedCount   = $result->filter(fn($r) => $r['attendance'] !== null)->count();
        $unmarkedCount = $result->count() - $markedCount;

        return $this->success([
            'date'     => $today,
            'total'    => $result->count(),
            'marked'   => $markedCount,
            'unmarked' => $unmarkedCount,
            'records'  => $result->values(),
        ]);
    }
}
