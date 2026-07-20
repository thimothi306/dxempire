<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('zone', 50)->nullable();
            $table->string('row', 20)->nullable();
            $table->string('shelf', 20)->nullable();
            $table->unsignedInteger('capacity')->default(50);
            $table->unsignedInteger('current_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('bins');
    }
};
