<?php

namespace Database\Seeders;

use App\Models\CatalogImage;
use Illuminate\Database\Seeder;

/**
 * Placeholder catalog photos so the app dev has non-null image_url values
 * to test against immediately. Replace with real product photography via
 * POST /api/v1/admin/catalog-images.
 */
class CatalogImageSeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            ['Apple', 'iPhone 13', 'phone', 'https://placehold.co/600x600/1d1d1f/ffffff?text=iPhone+13'],
            ['Apple', 'iPhone 14 Pro', 'phone', 'https://placehold.co/600x600/1d1d1f/ffffff?text=iPhone+14+Pro'],
            ['Samsung', 'Galaxy S22', 'phone', 'https://placehold.co/600x600/1428a0/ffffff?text=Galaxy+S22'],
            ['Samsung', 'Galaxy S23 Ultra', 'phone', 'https://placehold.co/600x600/1428a0/ffffff?text=Galaxy+S23+Ultra'],
            ['OnePlus', 'OnePlus 11', 'phone', 'https://placehold.co/600x600/f5010c/ffffff?text=OnePlus+11'],
            ['Xiaomi', 'Redmi Note 12', 'phone', 'https://placehold.co/600x600/ff6900/ffffff?text=Redmi+Note+12'],
            ['Apple', 'MacBook Air M2', 'laptop', 'https://placehold.co/600x600/1d1d1f/ffffff?text=MacBook+Air+M2'],
            ['Dell', 'XPS 13', 'laptop', 'https://placehold.co/600x600/007db8/ffffff?text=Dell+XPS+13'],
            ['HP', 'Pavilion 15', 'laptop', 'https://placehold.co/600x600/0096d6/ffffff?text=HP+Pavilion+15'],
            ['Apple', 'AirPods Pro', 'accessory', 'https://placehold.co/600x600/1d1d1f/ffffff?text=AirPods+Pro'],
        ];

        foreach ($images as [$brand, $model, $category, $url]) {
            CatalogImage::updateOrCreate(
                ['brand' => $brand, 'model' => $model, 'category' => $category],
                ['image_url' => $url]
            );
        }

        $this->command->info('✅ Catalog images seeded: ' . count($images) . ' placeholder photos');
    }
}
