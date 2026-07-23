<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CatalogImage;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Partner Web/App Catalog — browse in-stock products.
 * Partner selects a brand → sees all grades (S1–S5) of that brand's mobiles.
 * Products are individual units, so results are aggregated by model + grade
 * (available quantity + price) instead of listing every physical unit.
 */
class PartnerCatalogController extends Controller
{
    use ApiResponse;

    /**
     * List distinct brands available in stock (for the brand selector).
     * Optional ?category=phone|laptop
     */
    public function brands(Request $request): JsonResponse
    {
        $brands = Product::where('status', 'in_stock')
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->select('brand', DB::raw('COUNT(*) as available_qty'))
            ->groupBy('brand')
            ->orderBy('brand')
            ->get();

        $brandImages = $this->brandImageMap();
        $brands->transform(function ($b) use ($brandImages) {
            $b->image_url = $brandImages[$b->brand] ?? null;
            return $b;
        });

        return $this->success($brands);
    }

    /**
     * brand => image_url. CatalogImage is keyed by brand+model+category (no
     * brand-level image exists), so this picks one representative photo per
     * brand — its earliest-uploaded model image — for the brand selector tile.
     */
    private function brandImageMap(): array
    {
        return CatalogImage::query()
            ->orderBy('id')
            ->get(['brand', 'image_url'])
            ->groupBy('brand')
            ->map(fn($group) => $group->first()->image_url)
            ->all();
    }

    /**
     * Catalog listing — ONE row per model, with its available grades listed
     * inside it (grades_available + a richer per-grade qty/price breakdown).
     * Filters: ?brand=Apple  ?category=phone  ?grade=S1  ?search=iphone
     */
    public function index(Request $request): JsonResponse
    {
        $rows = Product::where('status', 'in_stock')
            ->when($request->brand, fn($q) => $q->where('brand', $request->brand))
            ->when($request->category, fn($q) => $q->where('category', $request->category))
            ->when($request->grade, fn($q) => $q->where('grade', $request->grade))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('brand', 'like', "%{$request->search}%")
                  ->orWhere('model', 'like', "%{$request->search}%");
            }))
            ->select(
                'brand',
                'model',
                'category',
                'grade',
                DB::raw('COUNT(*) as available_qty'),
                DB::raw('ROUND(MIN(selling_price), 2) as price_from'),
                DB::raw('ROUND(MAX(selling_price), 2) as price_to')
            )
            ->groupBy('brand', 'model', 'category', 'grade')
            ->orderBy('brand')
            ->orderBy('model')
            ->orderBy('grade')
            ->get();

        $images = $this->imageMap();

        $models = $rows
            ->groupBy(fn($r) => $r->brand . '|' . $r->model . '|' . $r->category)
            ->map(function ($group) use ($images) {
                $first = $group->first();
                return [
                    'brand'            => $first->brand,
                    'model'            => $first->model,
                    'category'         => $first->category,
                    'image_url'        => $images[$first->brand . '|' . $first->model . '|' . $first->category] ?? null,
                    'total_available'  => $group->sum('available_qty'),
                    'price_from'       => (float) $group->min('price_from'),
                    'price_to'         => (float) $group->max('price_to'),
                    'grades_available' => $group->pluck('grade')->values(),
                    'grades'           => $group->map(fn($r) => [
                        'grade'         => $r->grade,
                        'available_qty' => $r->available_qty,
                        'price_from'    => (float) $r->price_from,
                        'price_to'      => (float) $r->price_to,
                    ])->values(),
                ];
            })
            ->values();

        return $this->success($models);
    }

    /** brand|model|category => image_url lookup map, built once per request. */
    private function imageMap(): array
    {
        return CatalogImage::query()
            ->get(['brand', 'model', 'category', 'image_url'])
            ->mapWithKeys(fn($img) => [$img->brand . '|' . $img->model . '|' . $img->category => $img->image_url])
            ->all();
    }

    /**
     * Grades breakdown for a specific brand+model (e.g. tap a phone → see all grades).
     * ?brand=Apple&model=iPhone 14 Pro
     */
    public function grades(Request $request): JsonResponse
    {
        $request->validate([
            'brand' => ['required', 'string'],
            'model' => ['required', 'string'],
        ]);

        $grades = Product::where('status', 'in_stock')
            ->where('brand', $request->brand)
            ->where('model', $request->model)
            ->select(
                'grade',
                DB::raw('COUNT(*) as available_qty'),
                DB::raw('ROUND(MIN(selling_price), 2) as price_from'),
                DB::raw('ROUND(MAX(selling_price), 2) as price_to')
            )
            ->groupBy('grade')
            ->orderBy('grade')
            ->get();

        $imageUrl = CatalogImage::where('brand', $request->brand)
            ->where('model', $request->model)
            ->value('image_url');

        return $this->success([
            'brand'     => $request->brand,
            'model'     => $request->model,
            'image_url' => $imageUrl,
            'grades'    => $grades,
        ]);
    }
}
