<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model-level stock photos for the partner catalog (Option A: one photo per
 * brand+model+category, not per physical unit/IMEI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_images', function (Blueprint $table) {
            $table->id();
            $table->string('brand');
            $table->string('model');
            $table->enum('category', ['phone', 'laptop', 'accessory']);
            $table->string('image_url', 2048);
            $table->timestamps();

            $table->unique(['brand', 'model', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_images');
    }
};
