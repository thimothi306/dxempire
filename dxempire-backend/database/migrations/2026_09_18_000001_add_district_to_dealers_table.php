<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('district', 100)->nullable()->after('state');
            $table->index('district');
        });
    }

    public function down()
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropIndex(['district']);
            $table->dropColumn('district');
        });
    }
};
