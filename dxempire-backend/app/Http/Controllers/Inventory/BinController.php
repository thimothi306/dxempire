<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\MoveBinRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Bin;
use App\Models\BinMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BinController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $bins = Bin::withCount('products')
            ->when($request->zone, fn($q) => $q->where('zone', $request->zone))
            ->orderBy('code')
            ->paginate(100);

        return $this->paginated($bins);
    }

    public function move(MoveBinRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->product_id);
        $newBin  = Bin::findOrFail($request->bin_id);

        if (!$newBin->hasCapacity()) {
            return $this->error(
                "Bin {$newBin->code} is full. Capacity: {$newBin->capacity}, Current: {$newBin->current_count}.",
                422
            );
        }

        if ($product->bin_id === $newBin->id) {
            return $this->error('Product is already in this bin.', 422);
        }

        DB::beginTransaction();

        try {
            $oldBinId = $product->bin_id;

            // Decrement old bin count
            if ($oldBinId) {
                Bin::where('id', $oldBinId)->decrement('current_count');
            }

            // Increment new bin count
            $newBin->increment('current_count');

            // Move product
            $product->update(['bin_id' => $newBin->id]);

            // Audit trail
            BinMovement::create([
                'product_id'  => $product->id,
                'from_bin_id' => $oldBinId,
                'to_bin_id'   => $newBin->id,
                'moved_by'    => $request->user()->id,
                'moved_at'    => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Bin move failed: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'product_id' => $product->id,
            'bin'        => $newBin->only(['id', 'code', 'current_count', 'capacity']),
        ], "Product moved to bin {$newBin->code}.");
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => ['required', 'string', 'max:50', 'unique:bins,code'],
            'zone'     => ['nullable', 'string', 'max:50'],
            'row'      => ['nullable', 'string', 'max:50'],
            'shelf'    => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $bin = Bin::create([
            'code'          => $request->code,
            'zone'          => $request->zone,
            'row'           => $request->row,
            'shelf'         => $request->shelf,
            'capacity'      => $request->capacity ?? 50,
            'current_count' => 0,
        ]);

        return $this->created($bin, 'Bin created.');
    }

    public function products(Request $request, Bin $bin): JsonResponse
    {
        $products = $bin->products()
            ->with(['supplier'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return $this->paginated($products);
    }
}
