<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Four document types from the client's Partner Profile form had no
 * backing column at all: Trade Licence, the GST document itself (only the
 * GST number was stored), and Shop Document (Front/Inside).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('trade_licence_document_path')->nullable()->after('signed_agreement_path');
            $table->string('gst_document_path')->nullable()->after('trade_licence_document_path');
            $table->string('shop_document_front_path')->nullable()->after('gst_document_path');
            $table->string('shop_document_inside_path')->nullable()->after('shop_document_front_path');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn([
                'trade_licence_document_path', 'gst_document_path',
                'shop_document_front_path', 'shop_document_inside_path',
            ]);
        });
    }
};
