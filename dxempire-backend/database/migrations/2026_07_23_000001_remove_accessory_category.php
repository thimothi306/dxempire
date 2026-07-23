<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accessories are out of scope — only phone and laptop are supported.
 * Deletes existing accessory rows, then shrinks the ENUM columns so a new
 * accessory record can't be created again at the DB level (defense in
 * depth on top of the app-level validation rules, which are also updated).
 */
return new class extends Migration
{
    public function up(): void
    {
        $accessoryIds = DB::table('products')->where('category', 'accessory')->pluck('id');

        // Dependents first (qc_records/bin_movements/order_items all restrictOnDelete
        // the product FK, so products can't be removed while these still reference them).
        DB::table('qc_records')->whereIn('product_id', $accessoryIds)->delete();
        DB::table('bin_movements')->whereIn('product_id', $accessoryIds)->delete();
        DB::table('order_items')->whereIn('product_id', $accessoryIds)->delete();

        DB::table('products')->whereIn('id', $accessoryIds)->delete();
        DB::table('catalog_images')->where('category', 'accessory')->delete();
        // Offers with applicable_to='accessory' → fall back to 'all' rather
        // than deleting the offer itself (the offer/discount is still valid,
        // it just no longer needs to name a category that doesn't exist).
        DB::table('offers')->where('applicable_to', 'accessory')->update(['applicable_to' => 'all']);

        DB::statement("ALTER TABLE products MODIFY COLUMN category ENUM('phone','laptop') NOT NULL");
        DB::statement("ALTER TABLE catalog_images MODIFY COLUMN category ENUM('phone','laptop') NOT NULL");
        DB::statement("ALTER TABLE offers MODIFY COLUMN applicable_to ENUM('all','phone','laptop') NOT NULL DEFAULT 'all'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN category ENUM('phone','laptop','accessory') NOT NULL");
        DB::statement("ALTER TABLE catalog_images MODIFY COLUMN category ENUM('phone','laptop','accessory') NOT NULL");
        DB::statement("ALTER TABLE offers MODIFY COLUMN applicable_to ENUM('all','phone','laptop','accessory') NOT NULL DEFAULT 'all'");
    }
};
