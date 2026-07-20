<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peti_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 30)->unique();
            $table->enum('type', ['internal', 'dealer']);
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->unsignedBigInteger('to_dealer_id')->nullable();
            $table->json('items');
            $table->integer('total_units')->default(0);
            $table->decimal('total_value', 12, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();

            $table->foreign('to_dealer_id')->references('id')->on('dealers')->nullOnDelete();
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peti_transfers');
    }
};
