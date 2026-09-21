<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same client-specified form as the partner registration screen, applied to
 * the admin panel's "Add Employee" flow: structured address, mandatory bank
 * details, optional KYC documents. Every field here is new — the live
 * `employees` table only ever had the lean columns from its actual create
 * migration (2026_05_15_100014); a second, differently-timestamped
 * create_employees_table migration exists in the repo with extra columns
 * (pan_number, aadhar_number, bank_account, etc.) but never actually ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('village_street', 150)->nullable()->after('employee_code');
            $table->string('post_office', 100)->nullable()->after('village_street');
            $table->string('police_station', 100)->nullable()->after('post_office');
            $table->string('district', 100)->nullable()->after('police_station');
            $table->string('state', 100)->nullable()->after('district');
            $table->string('pincode', 10)->nullable()->after('state');

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
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'village_street', 'post_office', 'police_station', 'district', 'state', 'pincode',
                'bank_account_number', 'account_holder_name', 'bank_name', 'ifsc_code',
                'aadhaar_number', 'pan_number', 'aadhaar_document_path', 'pan_document_path',
                'passport_photo_path', 'education_certificate_path', 'bank_passbook_path', 'signed_agreement_path',
            ]);
        });
    }
};
