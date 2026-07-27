<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-attendance support: selfie photo + GPS coordinates captured at
 * check-in and check-out from the staff mobile app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->string('check_in_selfie', 2048)->nullable()->after('check_in');
            $table->decimal('check_in_lat', 10, 7)->nullable()->after('check_in_selfie');
            $table->decimal('check_in_lng', 10, 7)->nullable()->after('check_in_lat');

            $table->string('check_out_selfie', 2048)->nullable()->after('check_out');
            $table->decimal('check_out_lat', 10, 7)->nullable()->after('check_out_selfie');
            $table->decimal('check_out_lng', 10, 7)->nullable()->after('check_out_lat');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_selfie', 'check_in_lat', 'check_in_lng',
                'check_out_selfie', 'check_out_lat', 'check_out_lng',
            ]);
        });
    }
};
