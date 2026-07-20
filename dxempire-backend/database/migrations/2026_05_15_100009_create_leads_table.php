<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['b2b_inquiry', 'website', 'referral', 'walk_in', 'marketplace'])->default('website');
            $table->string('contact_name');
            $table->string('phone', 20)->nullable();
            $table->string('business_name')->nullable();
            $table->enum('stage', ['new', 'contacted', 'quoted', 'negotiating', 'won', 'lost'])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contact_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('leads');
    }
};
