<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        static $imeiSeq = 100000000000000;
        $imeiSeq++;

        return [
            'imei'           => (string) $imeiSeq,
            'brand'          => fake()->randomElement(['Apple', 'Samsung', 'OnePlus', 'Xiaomi']),
            'model'          => fake()->randomElement(['iPhone 13', 'Galaxy S22', 'Nord 3', 'Redmi Note 12']),
            'category'       => fake()->randomElement(['phone', 'laptop', 'accessory']),
            'grade'          => fake()->randomElement(['S1', 'S2', 'S3']),
            'status'         => 'in_stock',
            'supplier_id'    => Supplier::factory(),
            'purchase_price' => fake()->randomFloat(2, 5000, 50000),
            'selling_price'  => fake()->randomFloat(2, 6000, 60000),
            'qc_passed_at'   => now(),
        ];
    }
}
