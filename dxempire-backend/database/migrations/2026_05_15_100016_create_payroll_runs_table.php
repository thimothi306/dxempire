<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->enum('status', ['draft', 'processed', 'paid'])->default('draft');
            $table->timestamp('processed_at')->nullable();
            $table->decimal('total_payout', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['month', 'year']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payroll_runs');
    }
};
