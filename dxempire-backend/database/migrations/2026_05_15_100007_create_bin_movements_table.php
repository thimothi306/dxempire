<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bin_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('to_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->foreignId('moved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bin_movements');
    }
};
