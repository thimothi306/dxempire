<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('offer_id')->nullable()->after('total_amount')->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['offer_id']);
            $table->dropColumn(['offer_id', 'discount_amount']);
        });
    }
};
