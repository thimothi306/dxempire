<?php

namespace Database\Seeders;

use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin',
            'sales',
            'district_manager',
            'area_manager',
            'warehouse_staff',
            'qc_engineer',
            'accounts',
            'hr_manager',
            'logistics',
            'b2b_partner',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
        }

        $this->command->info('✅ Roles created successfully!');
    }
}
