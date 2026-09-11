<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Raw SQL, not Schema::renameColumn() — this project doesn't have
    // doctrine/dbal installed, which renameColumn() requires on Laravel 9.
    public function up()
    {
        DB::statement('ALTER TABLE dealers CHANGE referral_code unique_code VARCHAR(6) NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE dealers CHANGE unique_code referral_code VARCHAR(6) NULL');
    }
};
