<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // e.g. S1
            $table->string('label', 100);          // e.g. "Excellent — like new"
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the 5 grades that already exist as real product data, so nothing
        // breaks — this is a like-for-like conversion, not a data change.
        $now = now();
        DB::table('grades')->insert([
            ['code' => 'S1', 'label' => 'S1 — Excellent', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S2', 'label' => 'S2 — Very Good', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S3', 'label' => 'S3 — Good',      'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S4', 'label' => 'S4 — Fair',      'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'S5', 'label' => 'S5 — Refurbished/Working', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
