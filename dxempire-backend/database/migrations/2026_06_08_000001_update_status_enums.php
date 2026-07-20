<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'reserved' to products.status and 'qc_pending' return path
        DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM(
            'received','qc_pending','in_stock','reserved','sold','returned','rejected','refurbishment'
        ) NOT NULL DEFAULT 'received'");

        // Add 'packed' to orders.status (code already uses 'packed', migration had 'packing')
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending','approved','picking','packing','packed','dispatched','delivered','cancelled','returned'
        ) NOT NULL DEFAULT 'pending'");

        // Add return_count to products for tracking re-QC cycles
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('return_count')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('return_count');
        });

        DB::statement("ALTER TABLE products MODIFY COLUMN status ENUM(
            'received','qc_pending','in_stock','sold','returned','rejected','refurbishment'
        ) NOT NULL DEFAULT 'received'");

        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM(
            'pending','approved','picking','packing','dispatched','delivered','cancelled','returned'
        ) NOT NULL DEFAULT 'pending'");
    }
};
