<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CatalogImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'category'  => ['required', 'string', 'in:phone,laptop'],
            'image_url' => ['required', 'url', 'max:2048'],
        ]);

        $image = CatalogImage::updateOrCreate(
            ['brand' => $data['brand'], 'model' => $data['model'], 'category' => $data['category']],
            ['image_url' => $data['image_url']]
        );

        return $this->success($image, 'Catalog image saved.');
    }

    /**
     * Upload an image file directly (multipart/form-data) for a brand+model+category.
     * Stored under public/uploads/catalog-images — deliberately NOT storage/app/public,
     * since php's symlink() is disabled on this host (php artisan storage:link fails
     * there), so the usual Storage-facade + public-disk-symlink approach won't serve.
     */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand'    => ['required', 'string'],
            'model'    => ['required', 'string'],
            'category' => ['required', 'string', 'in:phone,laptop'],
            'image'    => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $dir = public_path('uploads/catalog-images');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Deterministic filename per brand+model+category so re-uploading the
        // same model replaces its old file instead of piling up duplicates.
        $slug = Str::slug($data['brand'] . '-' . $data['model'] . '-' . $data['category']);
        $file = $request->file('image');
        $filename = $slug . '.' . $file->getClientOriginalExtension();

        $file->move($dir, $filename);

        $imageUrl = rtrim(config('app.url'), '/') . '/uploads/catalog-images/' . $filename;

        $image = CatalogImage::updateOrCreate(
            ['brand' => $data['brand'], 'model' => $data['model'], 'category' => $data['category']],
            ['image_url' => $imageUrl]
        );

        return $this->success($image, 'Catalog image uploaded.');
    }

    public function destroy(CatalogImage $catalogImage): JsonResponse
    {
        $catalogImage->delete();
        return $this->success(null, 'Catalog image removed.');
    }
}
