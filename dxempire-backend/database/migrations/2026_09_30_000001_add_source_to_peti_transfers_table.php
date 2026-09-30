<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes a staff-initiated push (admin decides to send a partner a
 * peti) from a partner-initiated request (partner orders one from the
 * mobile app) — same table, same status flow, different origin so the
 * admin panel can tell which ones still need staff review/pricing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peti_transfers', function (Blueprint $table) {
            $table->enum('source', ['staff', 'partner'])->default('staff')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('peti_transfers', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
