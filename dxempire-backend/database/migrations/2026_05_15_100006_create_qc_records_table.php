<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('qc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('engineer_id')->constrained('users')->restrictOnDelete();
            $table->enum('grade', ['S1', 'S2', 'S3', 'S4', 'S5'])->nullable();
            $table->text('condition_notes')->nullable();
            $table->enum('outcome', ['pass', 'repair', 'reject']);
            $table->timestamp('graded_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('qc_records');
    }
};
