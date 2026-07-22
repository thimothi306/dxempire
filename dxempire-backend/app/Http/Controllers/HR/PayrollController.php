<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PayrollController extends Controller
{
    use ApiResponse;

    public function __construct(private PayrollService $payrollService) {}

    public function index(Request $request): JsonResponse
    {
        $runs = PayrollRun::withCount('items')
            ->when($request->year, fn($q) => $q->where('year', $request->year))
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate($request->integer('per_page', 12));

        return $this->paginated($runs);
    }

    /**
     * Create a draft payroll run for a given month/year.
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year'  => ['required', 'integer', 'min:2020'],
        ]);

        $existing = PayrollRun::where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        if ($existing) {
            return $this->error(
                "A payroll run already exists for {$request->month}/{$request->year}. Status: {$existing->status}.",
                422
            );
        }

        $run = PayrollRun::create([
            'month'  => $request->month,
            'year'   => $request->year,
            'status' => 'draft',
        ]);

        return $this->created($run, 'Payroll run created as draft.');
    }

    public function createAndProcess(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year'  => ['required', 'integer', 'min:2020'],
        ]);

        $existing = PayrollRun::where('month', $request->month)
            ->where('year', $request->year)
            ->first();

        $run = $existing ?? PayrollRun::create([
            'month'  => $request->month,
            'year'   => $request->year,
            'status' => 'draft',
        ]);

        if ($run->status === 'draft') {
            DB::beginTransaction();
            try {
                $totalPayout = $this->payrollService->process($run);
                $run->update(['status' => 'processed', 'processed_at' => now(), 'total_payout' => $totalPayout]);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return $this->success($run->fresh(['items.employee.user:id,name']), 'Payroll processed.');
    }

    public function show(PayrollRun $payrollRun): JsonResponse
    {
        return $this->success($payrollRun->load(['items.employee.user:id,name']));
    }

    /**
     * Process a draft run — compute all employee payroll items from attendance.
     */
    public function process(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'draft') {
            return $this->error("Only draft runs can be processed. Current status: {$payrollRun->status}.", 422);
        }

        DB::beginTransaction();
        try {
            $totalPayout = $this->payrollService->process($payrollRun);

            $payrollRun->update([
                'status'       => 'processed',
                'processed_at' => now(),
                'total_payout' => $totalPayout,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this->success(
            $payrollRun->fresh(['items.employee.user:id,name']),
            "Payroll processed. Total payout: ₹" . number_format($totalPayout, 2)
        );
    }

    /**
     * Mark a processed run as paid (disbursement confirmed).
     */
    public function markPaid(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status !== 'processed') {
            return $this->error("Only processed runs can be marked as paid.", 422);
        }

        $payrollRun->update(['status' => 'paid']);

        return $this->success($payrollRun->fresh(), 'Payroll run marked as paid.');
    }

    /**
     * List all payroll items for a run with employee details.
     */
    public function items(PayrollRun $payrollRun): JsonResponse
    {
        $items = $payrollRun->items()
            ->with('employee.user:id,name,phone')
            ->get()
            ->map(fn($item) => [
                'id'           => $item->id,
                'employee_id'  => $item->employee_id,
                'emp_code'     => 'EMP-' . str_pad($item->employee_id, 4, '0', STR_PAD_LEFT),
                'name'         => $item->employee->name ?? $item->employee->user?->name,
                'phone'        => $item->employee->user?->phone,
                'department'   => $item->employee->department,
                'days_worked'  => $item->days_worked,
                'basic'        => $item->basic,
                'deductions'   => $item->deductions,
                'net_salary'   => $item->net_salary,
                'slip_path'    => $item->slip_path,
            ]);

        return $this->success([
            'run'          => $payrollRun->only(['id', 'month', 'year', 'status', 'total_payout']),
            'employee_count' => $items->count(),
            'items'        => $items,
        ]);
    }

    /**
     * Generate and download a pay slip PDF for a single PayrollItem.
     */
    public function downloadSlip(PayrollRun $payrollRun, PayrollItem $payrollItem): Response|JsonResponse
    {
        if ($payrollItem->payroll_run_id !== $payrollRun->id) {
            return $this->error('Pay slip does not belong to this run.', 404);
        }

        // Regenerate if missing
        if (!$payrollItem->slip_path || !Storage::exists($payrollItem->slip_path)) {
            try {
                $this->payrollService->generateSlip($payrollItem);
                $payrollItem->refresh();
            } catch (\Throwable $e) {
                return $this->error('Slip generation failed: ' . $e->getMessage(), 500);
            }
        }

        $empName = $payrollItem->employee->name ?? $payrollItem->employee->user?->name ?? 'employee';
        $filename = "payslip_{$payrollRun->year}_{$payrollRun->month}_{$empName}.pdf";

        return response(Storage::get($payrollItem->slip_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Generate pay slips for all items in a processed run.
     */
    public function generateAllSlips(PayrollRun $payrollRun): JsonResponse
    {
        if ($payrollRun->status === 'draft') {
            return $this->error('Process the payroll run before generating slips.', 422);
        }

        $items     = $payrollRun->items()->with(['employee.user', 'employee.attendance'])->get();
        $generated = 0;
        $failed    = [];

        foreach ($items as $item) {
            try {
                $this->payrollService->generateSlip($item);
                $generated++;
            } catch (\Throwable $e) {
                $failed[] = $item->employee_id;
            }
        }

        return $this->success([
            'generated' => $generated,
            'failed'    => $failed,
        ], "{$generated} pay slip(s) generated.");
    }
}
