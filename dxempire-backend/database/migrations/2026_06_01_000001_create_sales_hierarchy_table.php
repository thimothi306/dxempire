<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_hierarchy', function (Blueprint $table) {
            $table->id();
            $table->string('tree_id', 20)->unique();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->enum('hierarchy_role', ['ceo', 'state_manager', 'area_manager', 'district_manager', 'salesman']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('state', 100)->nullable();
            $table->string('area', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('sales_hierarchy')->nullOnDelete();
            $table->index(['hierarchy_role', 'is_active']);
            $table->index('parent_id');
            $table->index('state');
        });

        // Add assigned_salesman_id to dealers
        Schema::table('dealers', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_salesman_id')->nullable()->after('user_id');
            $table->foreign('assigned_salesman_id')->references('id')->on('sales_hierarchy')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropForeign(['assigned_salesman_id']);
            $table->dropColumn('assigned_salesman_id');
        });
        Schema::dropIfExists('sales_hierarchy');
    }
};
