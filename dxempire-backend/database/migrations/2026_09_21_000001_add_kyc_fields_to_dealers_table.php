<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Client-specified partner registration form: structured address (village/
 * street, post office, police station — district/state/pincode already
 * exist), mandatory bank payout details, and optional KYC document uploads
 * that can be completed after registration (kyc_status stays 'pending'
 * until an admin reviews and verifies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('village_street', 150)->nullable()->after('district');
            $table->string('post_office', 100)->nullable()->after('village_street');
            $table->string('police_station', 100)->nullable()->after('post_office');

            $table->string('bank_account_number', 30)->nullable()->after('pincode');
            $table->string('account_holder_name', 150)->nullable()->after('bank_account_number');
            $table->string('bank_name', 150)->nullable()->after('account_holder_name');
            $table->string('ifsc_code', 15)->nullable()->after('bank_name');

            $table->string('aadhaar_number', 20)->nullable()->after('ifsc_code');
            $table->string('pan_number', 15)->nullable()->after('aadhaar_number');
            $table->string('aadhaar_document_path')->nullable()->after('pan_number');
            $table->string('pan_document_path')->nullable()->after('aadhaar_document_path');
            $table->string('passport_photo_path')->nullable()->after('pan_document_path');
            $table->string('education_certificate_path')->nullable()->after('passport_photo_path');
            $table->string('bank_passbook_path')->nullable()->after('education_certificate_path');
            $table->string('signed_agreement_path')->nullable()->after('bank_passbook_path');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn([
                'village_street', 'post_office', 'police_station',
                'bank_account_number', 'account_holder_name', 'bank_name', 'ifsc_code',
                'aadhaar_number', 'pan_number', 'aadhaar_document_path', 'pan_document_path',
                'passport_photo_path', 'education_certificate_path', 'bank_passbook_path', 'signed_agreement_path',
            ]);
        });
    }
};
