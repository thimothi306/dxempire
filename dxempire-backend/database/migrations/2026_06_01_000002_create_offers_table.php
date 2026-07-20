<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('discount_value', 10, 2);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->enum('applicable_to', ['all', 'phone', 'laptop', 'accessory'])->default('all');
            $table->enum('applicable_grade', ['all', 'S1', 'S2', 'S3', 'S4', 'S5'])->default('all');
            $table->enum('customer_type', ['all', 'b2b', 'retail'])->default('all');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->integer('max_usage')->nullable();
            $table->integer('usage_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['is_active', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
