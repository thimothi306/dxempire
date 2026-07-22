<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin "Add Employee" form creates a new person directly (name/phone/
 * email/employee_code), not just links an existing system-login user. Adds
 * those fields and makes user_id optional (an employee doesn't need a login).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
            $table->string('phone', 20)->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('employee_code', 30)->nullable()->unique()->after('email');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract'])->default('full_time')->after('designation');
        });

        // user_id is only needed when an employee also has a system login.
        // Raw SQL (no doctrine/dbal installed, so Blueprint::change() is unavailable).
        DB::statement('ALTER TABLE employees MODIFY user_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['name', 'phone', 'email', 'employee_code', 'employment_type']);
        });
    }
};
