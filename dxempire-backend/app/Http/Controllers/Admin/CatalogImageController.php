<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CatalogImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of model-level catalog photos (brand+model+category → one image).
 * Consumed by the partner catalog (GET /partner/catalog, /partner/catalog/grades).
 */
class CatalogImageController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(CatalogImage::orderBy('brand')->orderBy('model')->get());
    }

    /** Create or replace the image for a brand+model+category. */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand'     => ['required', 'string'],
            'model'     => ['required', 'string'],
            'category'  => ['required', 'string', 'in:phone,laptop,accessory'],
            'image_url' => ['required', 'url', 'max:2048'],
        ]);

        $image = CatalogImage::updateOrCreate(
            ['brand' => $data['brand'], 'model' => $data['model'], 'category' => $data['category']],
            ['image_url' => $data['image_url']]
        );

        return $this->success($image, 'Catalog image saved.');
    }

    public function destroy(CatalogImage $catalogImage): JsonResponse
    {
        $catalogImage->delete();
        return $this->success(null, 'Catalog image removed.');
    }
}
