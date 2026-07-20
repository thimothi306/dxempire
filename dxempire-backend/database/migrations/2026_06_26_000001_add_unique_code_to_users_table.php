<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'unique_code')) {
                $table->string('unique_code')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'parent_unique_code')) {
                $table->string('parent_unique_code')->nullable()->after('unique_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'unique_code')) {
                $table->dropColumn('unique_code');
            }
            if (Schema::hasColumn('users', 'parent_unique_code')) {
                $table->dropColumn('parent_unique_code');
            }
        });
    }
};
