<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('imei', 20)->nullable()->unique();
            $table->string('serial_number', 100)->nullable();
            $table->enum('category', ['phone', 'laptop', 'accessory']);
            $table->string('brand', 100);
            $table->string('model', 200);
            $table->enum('grade', ['S1', 'S2', 'S3', 'S4', 'S5'])->nullable();
            $table->enum('status', ['received', 'qc_pending', 'in_stock', 'sold', 'returned', 'rejected', 'refurbishment'])->default('received');
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->decimal('purchase_price', 10, 2);
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('qc_passed_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'category']);
            $table->index('imei');
            $table->index('bin_id');
            $table->index('supplier_id');
            $table->index('grade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};
