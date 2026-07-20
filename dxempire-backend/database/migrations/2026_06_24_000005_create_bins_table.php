<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->string('bin_number')->unique();
            $table->string('warehouse_section');
            $table->string('bin_type'); // shelf, rack, pallet
            $table->integer('capacity');
            $table->integer('current_items')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('warehouse_section');
        });

        Schema::create('bin_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bin_id')->constrained('bins')->onDelete('cascade');
            $table->foreignId('inventory_id')->constrained('inventory')->onDelete('cascade');
            $table->integer('quantity');
            $table->enum('movement_type', ['in', 'out', 'transfer'])->default('in');
            $table->string('from_bin')->nullable();
            $table->string('to_bin')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_movements');
        Schema::dropIfExists('bins');
    }
};
