<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('referral_code', 6)->nullable()->unique()->after('pincode');
            $table->foreignId('referred_by_dealer_id')->nullable()->after('referral_code')
                ->constrained('dealers')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_dealer_id');
            $table->dropColumn('referral_code');
        });
    }
};
