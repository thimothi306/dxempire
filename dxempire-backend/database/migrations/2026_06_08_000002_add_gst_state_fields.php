<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add state info to orders for GST determination
        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_state', 60)->nullable()->after('notes');
            $table->string('shipping_state', 60)->nullable()->after('billing_state');
        });

        // Add CGST/SGST/IGST breakdown to invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('billing_state', 60)->nullable()->after('dealer_id');
            $table->string('shipping_state', 60)->nullable()->after('billing_state');
            $table->enum('tax_type', ['intra', 'inter'])->default('inter')->after('shipping_state');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('gst_amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('igst_amount', 12, 2)->default(0)->after('sgst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['billing_state', 'shipping_state']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_state', 'shipping_state', 'tax_type', 'cgst_amount', 'sgst_amount', 'igst_amount']);
        });
    }
};
