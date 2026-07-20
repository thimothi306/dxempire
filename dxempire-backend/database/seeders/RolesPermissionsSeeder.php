<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'inventory.view', 'inventory.edit',
            'orders.view', 'orders.approve', 'orders.dispatch',
            'finance.view', 'finance.edit',
            'hr.view', 'hr.edit', 'payroll.run',
            'analytics.view', 'settings.edit',
            'procurement.view', 'procurement.edit',
            'qc.view', 'qc.grade',
            'crm.view', 'crm.edit',
            'dealers.view', 'dealers.edit',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'api']);
        }

        $roles = [
            'super_admin'    => Permission::all()->pluck('name')->toArray(),
            'sales'          => ['orders.view', 'orders.approve', 'inventory.view', 'analytics.view', 'crm.view', 'crm.edit', 'dealers.view'],
            'warehouse_staff'=> ['inventory.view', 'inventory.edit', 'orders.view', 'orders.dispatch', 'procurement.view', 'procurement.edit'],
            'qc_engineer'    => ['inventory.view', 'qc.view', 'qc.grade'],
            'packing_staff'  => ['orders.view', 'inventory.view'],
            'accounts'       => ['finance.view', 'finance.edit', 'analytics.view', 'orders.view'],
            'hr_manager'     => ['hr.view', 'hr.edit', 'payroll.run', 'users.view'],
            'b2b_partner'    => ['inventory.view', 'orders.view', 'dealers.view'],
            'logistics'      => ['orders.view', 'orders.dispatch', 'inventory.view'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
