<?php

namespace App\Http\Controllers\QC;

use App\Events\ProductRejected;
use App\Events\StockAdded;
use App\Http\Controllers\Controller;
use App\Http\Requests\QC\StoreGradeRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Product;
use App\Models\QcRecord;
use App\Services\GradePricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QcController extends Controller
{
    use ApiResponse;

    public function __construct(private GradePricingService $pricing) {}

    public function pending(Request $request): JsonResponse
    {
        // qc_pending = returned items (re-QC), received = fresh incoming stock
        $products = Product::with(['supplier', 'purchaseOrder'])
            ->whereIn('status', ['received', 'qc_pending'])
            ->orderByRaw("FIELD(status, 'qc_pending', 'received')")
            ->orderBy('created_at')
            ->paginate(50);

        return $this->paginated($products);
    }

    public function grade(StoreGradeRequest $request): JsonResponse
    {
        $product = Product::find($request->product_id);

        if (!in_array($product->status, ['received', 'qc_pending', 'refurbishment'])) {
            return $this->error('Product is not awaiting QC. Current status: ' . $product->status, 422);
        }

        DB::beginTransaction();

        try {
            $qcRecord = QcRecord::create([
                'product_id'      => $product->id,
                'engineer_id'     => $request->user()->id,
                'grade'           => $request->outcome === 'pass' ? $request->grade : null,
                'condition_notes' => $request->condition_notes,
                'outcome'         => $request->outcome,
                'graded_at'       => now(),
            ]);

            match ($request->outcome) {
                'pass'   => $this->handlePass($product, $request->grade),
                'repair' => $this->handleRepair($product),
                'reject' => $this->handleReject($product),
            };

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('QC grading failed: ' . $e->getMessage(), 500);
        }

        // Fire events after commit
        if ($request->outcome === 'pass') {
            event(new StockAdded($product->fresh()));
        } elseif ($request->outcome === 'reject') {
            event(new ProductRejected($product->fresh()));
        }

        return $this->created([
            'qc_record' => $qcRecord->toArray(),
            'product'   => $product->fresh()->only(['id', 'status', 'grade', 'selling_price']),
        ], 'QC grade recorded.');
    }

    private function handlePass(Product $product, string $grade): void
    {
        $prices = $this->pricing->calculateBothPrices((float) $product->purchase_price, $grade);

        $product->update([
            'grade'         => $grade,
            'status'        => 'in_stock',
            'selling_price' => $prices['selling_price'],
            'retail_price'  => $prices['retail_price'],
            'qc_passed_at'  => now(),
        ]);
    }

    private function handleRepair(Product $product): void
    {
        $product->update(['status' => 'refurbishment']);
    }

    private function handleReject(Product $product): void
    {
        $product->update(['status' => 'rejected']);
    }

    public function records(Request $request): JsonResponse
    {
        $records = QcRecord::with(['product', 'engineer'])
            ->when($request->outcome,   fn($q) => $q->where('outcome', $request->outcome))
            ->when($request->grade,     fn($q) => $q->where('grade', $request->grade))
            ->when($request->engineer_id, fn($q) => $q->where('engineer_id', $request->engineer_id))
            ->when($request->from,      fn($q) => $q->whereDate('graded_at', '>=', $request->from))
            ->when($request->to,        fn($q) => $q->whereDate('graded_at', '<=', $request->to))
            ->orderByDesc('graded_at')
            ->paginate(50);

        return $this->paginated($records);
    }

    public function sendToRefurbishment(Request $request): JsonResponse
    {
        $request->validate([
            'product_id'      => ['required', 'exists:products,id'],
            'condition_notes' => ['nullable', 'string'],
        ]);

        $product = Product::find($request->product_id);

        if (!in_array($product->status, ['received', 'in_stock'])) {
            return $this->error('Product cannot be sent to refurbishment from status: ' . $product->status, 422);
        }

        DB::beginTransaction();
        try {
            $qcRecord = QcRecord::create([
                'product_id'      => $product->id,
                'engineer_id'     => $request->user()->id,
                'grade'           => null,
                'condition_notes' => $request->condition_notes,
                'outcome'         => 'repair',
                'graded_at'       => now(),
            ]);

            $product->update(['status' => 'refurbishment']);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'qc_record' => $qcRecord,
            'product'   => $product->fresh()->only(['id', 'status', 'brand', 'model']),
        ], 'Product sent to refurbishment.');
    }

    public function refurbishment(Request $request): JsonResponse
    {
        $products = Product::with(['supplier', 'qcRecords.engineer'])
            ->where('status', 'refurbishment')
            ->orderBy('updated_at')
            ->paginate(50);

        return $this->paginated($products);
    }

    public function completeRefurbishment(Product $product): JsonResponse
    {
        if ($product->status !== 'refurbishment') {
            return $this->error('Product is not in refurbishment. Current status: ' . $product->status, 422);
        }

        // Re-enter QC cycle
        $product->update(['status' => 'received']);

        return $this->success(
            $product->fresh()->only(['id', 'status', 'brand', 'model']),
            'Product returned to QC queue.'
        );
    }

    public function stats(): JsonResponse
    {
        $total  = QcRecord::count();
        $passed = QcRecord::where('outcome', 'pass')->count();
        $repair = QcRecord::where('outcome', 'repair')->count();
        $reject = QcRecord::where('outcome', 'reject')->count();

        $byGrade = QcRecord::where('outcome', 'pass')
            ->selectRaw('grade, count(*) as count')
            ->groupBy('grade')
            ->pluck('count', 'grade');

        $todayTotal  = QcRecord::whereDate('graded_at', today())->count();
        $weekTotal   = QcRecord::whereBetween('graded_at', [now()->startOfWeek(), now()])->count();

        return $this->success([
            'total'      => $total,
            'pass_count' => $passed,
            'repair_count' => $repair,
            'reject_count' => $reject,
            'pass_rate'  => $total > 0 ? round(($passed / $total) * 100, 1) : 0,
            'by_grade'   => $byGrade,
            'today'      => $todayTotal,
            'this_week'  => $weekTotal,
            'pending_qc' => Product::where('status', 'received')->count(),
            'in_refurbishment' => Product::where('status', 'refurbishment')->count(),
        ]);
    }
}
