<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->string('gst_number');
            $table->string('buyer_name');
            $table->string('buyer_gst')->nullable();
            $table->string('buyer_address');
            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('sgst_amount', 12, 2);
            $table->decimal('cgst_amount', 12, 2);
            $table->decimal('igst_amount', 12, 2);
            $table->decimal('total_gst', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->date('invoice_date');
            $table->enum('status', ['draft', 'finalized', 'filed'])->default('draft');
            $table->string('hsncode')->nullable();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_invoices');
    }
};
