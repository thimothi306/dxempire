<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Staff mobile self-service attendance — check-in / check-out with a selfie
 * photo and GPS coordinates. The employee is resolved from the authenticated
 * user's token (no employee_id needed from the client), so a staff member can
 * only ever mark their OWN attendance.
 *
 * Selfies are stored under public/uploads/attendance (NOT storage/app/public —
 * php's symlink() is disabled on this host, so storage:link doesn't work here).
 */
class AttendanceController extends Controller
{
    use ApiResponse;

    /** Resolve the Employee record tied to the logged-in user, or null. */
    private function employee(Request $request): ?Employee
    {
        return Employee::where('user_id', $request->user()->id)->first();
    }

    /** Today's attendance for the logged-in user, so the app knows current state. */
    public function status(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        if (!$employee) {
            return $this->error('No employee profile is linked to your account. Contact HR.', 404);
        }

        $today = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        return $this->success([
            'employee_id'    => $employee->id,
            'name'           => $employee->name ?? $employee->user?->name,
            'date'           => now()->toDateString(),
            'checked_in'     => (bool) $today?->check_in,
            'checked_out'    => (bool) $today?->check_out,
            'attendance'     => $today,
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        if (!$employee) {
            return $this->error('No employee profile is linked to your account. Contact HR.', 404);
        }

        $data = $request->validate([
            'selfie'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $existing = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if ($existing && $existing->check_in) {
            return $this->error('You have already checked in today.', 422);
        }

        $when = isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now();

        $record = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => now()->toDateString()],
            [
                'status'          => 'present',
                'check_in'        => $when,
                'check_in_selfie' => $this->storeSelfie($request, $employee, 'in'),
                'check_in_lat'    => $data['latitude'] ?? null,
                'check_in_lng'    => $data['longitude'] ?? null,
            ]
        );

        return $this->success($record->fresh(), 'Checked in successfully.');
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        if (!$employee) {
            return $this->error('No employee profile is linked to your account. Contact HR.', 404);
        }

        $data = $request->validate([
            'selfie'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timestamp' => ['nullable', 'date'],
        ]);

        $record = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        if (!$record || !$record->check_in) {
            return $this->error('No check-in found for today. Please check in first.', 422);
        }
        if ($record->check_out) {
            return $this->error('You have already checked out today.', 422);
        }

        $when = isset($data['timestamp']) ? Carbon::parse($data['timestamp']) : now();

        $record->update([
            'check_out'        => $when,
            'check_out_selfie' => $this->storeSelfie($request, $employee, 'out'),
            'check_out_lat'    => $data['latitude'] ?? null,
            'check_out_lng'    => $data['longitude'] ?? null,
        ]);

        return $this->success($record->fresh(), 'Checked out successfully.');
    }

    /** Store the uploaded selfie (if any) and return its public URL, else null. */
    private function storeSelfie(Request $request, Employee $employee, string $which): ?string
    {
        if (!$request->hasFile('selfie')) {
            return null;
        }

        $dir = public_path('uploads/attendance');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $request->file('selfie');
        $filename = "att_{$employee->id}_" . now()->format('Ymd_His') . "_{$which}_" . Str::random(6)
            . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $filename);

        return rtrim(config('app.url'), '/') . '/uploads/attendance/' . $filename;
    }
}
