<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three columns (products.grade, qc_records.grade, offers.applicable_grade)
 * were DB-level ENUMs restricted to S1-S5 — meaning adding a 6th grade
 * required a schema migration, not an admin-panel action. Converts all three
 * to plain VARCHAR; validity is now enforced by the app (against the new
 * `grades` table) instead of the database. Existing values are untouched —
 * this is a column-type change only, not a data change.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products MODIFY grade VARCHAR(10) NULL");
        DB::statement("ALTER TABLE qc_records MODIFY grade VARCHAR(10) NULL");
        DB::statement("ALTER TABLE offers MODIFY applicable_grade VARCHAR(10) NOT NULL DEFAULT 'all'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY grade ENUM('S1','S2','S3','S4','S5') NULL");
        DB::statement("ALTER TABLE qc_records MODIFY grade ENUM('S1','S2','S3','S4','S5') NULL");
        DB::statement("ALTER TABLE offers MODIFY applicable_grade ENUM('all','S1','S2','S3','S4','S5') NOT NULL DEFAULT 'all'");
    }
};
