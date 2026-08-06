<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('incentive_enabled')->default(false)->after('basic_salary');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('incentive_enabled');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->decimal('incentive', 10, 2)->default(0)->after('basic');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['incentive_enabled', 'commission_rate']);
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('incentive');
        });
    }
};
