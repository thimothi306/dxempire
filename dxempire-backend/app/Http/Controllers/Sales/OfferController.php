<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $offers = Offer::with('createdBy:id,name')
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', (bool) $request->is_active))
            ->when($request->customer_type, fn($q) => $q->where('customer_type', $request->customer_type))
            ->orderByDesc('valid_from')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($offers);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'               => ['required', 'string', 'max:200'],
            'code'                => ['required', 'string', 'max:50', 'unique:offers,code'],
            'description'         => ['nullable', 'string'],
            'discount_type'       => ['required', 'in:percentage,fixed'],
            'discount_value'      => ['required', 'numeric', 'min:0'],
            'min_order_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'applicable_to'       => ['required', 'in:all,phone,laptop,accessory'],
            'applicable_grade'    => ['required', 'in:all,S1,S2,S3,S4,S5'],
            'customer_type'       => ['required', 'in:all,b2b,retail'],
            'valid_from'          => ['required', 'date'],
            'valid_to'            => ['required', 'date', 'after:valid_from'],
            'max_usage'           => ['nullable', 'integer', 'min:1'],
        ]);

        $offer = Offer::create([
            ...$request->only([
                'title', 'code', 'description', 'discount_type', 'discount_value',
                'min_order_amount', 'max_discount_amount', 'applicable_to',
                'applicable_grade', 'customer_type', 'valid_from', 'valid_to', 'max_usage',
            ]),
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        return $this->created($offer, 'Offer created.');
    }

    public function show(Offer $offer): JsonResponse
    {
        return $this->success($offer->load('createdBy:id,name'));
    }

    public function update(Request $request, Offer $offer): JsonResponse
    {
        $request->validate([
            'title'               => ['sometimes', 'string', 'max:200'],
            'discount_type'       => ['sometimes', 'in:percentage,fixed'],
            'discount_value'      => ['sometimes', 'numeric', 'min:0'],
            'min_order_amount'    => ['nullable', 'numeric', 'min:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'applicable_to'       => ['sometimes', 'in:all,phone,laptop,accessory'],
            'applicable_grade'    => ['sometimes', 'in:all,S1,S2,S3,S4,S5'],
            'customer_type'       => ['sometimes', 'in:all,b2b,retail'],
            'valid_from'          => ['sometimes', 'date'],
            'valid_to'            => ['sometimes', 'date'],
            'max_usage'           => ['nullable', 'integer', 'min:1'],
            'is_active'           => ['boolean'],
        ]);

        $offer->update($request->only([
            'title', 'description', 'discount_type', 'discount_value',
            'min_order_amount', 'max_discount_amount', 'applicable_to',
            'applicable_grade', 'customer_type', 'valid_from', 'valid_to',
            'max_usage', 'is_active',
        ]));

        return $this->success($offer->fresh(), 'Offer updated.');
    }

    public function destroy(Offer $offer): JsonResponse
    {
        $offer->update(['is_active' => false]);

        return $this->success(null, 'Offer deactivated.');
    }

    public function validateCode(Request $request): JsonResponse
    {
        $request->validate([
            'code'        => ['required', 'string'],
            'order_total' => ['required', 'numeric', 'min:0'],
        ]);

        $offer = Offer::where('code', strtoupper($request->code))->first();

        if (!$offer || !$offer->isValid()) {
            return $this->error('Invalid or expired offer code.', 422);
        }

        $discount = $offer->calculateDiscount((float) $request->order_total);

        return $this->success([
            'offer'         => $offer->only(['id', 'title', 'code', 'discount_type', 'discount_value']),
            'discount'      => $discount,
            'final_total'   => round((float) $request->order_total - $discount, 2),
        ], 'Offer applied.');
    }

    public function active(): JsonResponse
    {
        $offers = Offer::where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_to', '>=', now())
            ->whereRaw('(max_usage IS NULL OR usage_count < max_usage)')
            ->orderBy('valid_to')
            ->get();

        return $this->success($offers);
    }
}
