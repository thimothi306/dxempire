<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')
                ->constrained('warehouses')->nullOnDelete();
        });

        // Backfill: create one "Default Warehouse" from the existing Delhivery
        // pickup Settings (if set) so single-warehouse installs need zero setup,
        // and attach every existing bin to it so nothing breaks.
        $get = fn(string $key) => DB::table('settings')->where('key', $key)->value('value');

        $warehouseId = DB::table('warehouses')->insertGetId([
            'name'       => $get('warehouse_name') ?: 'Default Warehouse',
            'code'       => 'WH-01',
            'phone'      => $get('warehouse_phone'),
            'email'      => $get('warehouse_email'),
            'address'    => $get('warehouse_address'),
            'city'       => $get('warehouse_city'),
            'state'      => $get('warehouse_state'),
            'pincode'    => $get('warehouse_pincode'),
            'is_default' => true,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bins')->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
