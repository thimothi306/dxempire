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

        // One permission per route-group boundary that already exists in
        // routes/api.php — this mirrors today's role-based gating exactly,
        // just made toggleable instead of hardcoded in route middleware.
        $permissions = [
            'users.manage', 'catalog_images.manage', 'audit_logs.view', 'settings.edit',
            'inventory.view', 'inventory.export',
            'procurement.view', 'procurement.edit',
            'bins.manage', 'warehouses.manage', 'grades.manage',
            'qc.view', 'qc.grade',
            'crm.view', 'crm.edit', 'support.manage',
            'orders.view', 'orders.create', 'orders.fulfill', 'orders.approve',
            'finance.view', 'finance.edit',
            'hr.view', 'hr.edit', 'payroll.run',
            'logistics.manage', 'logistics.configure',
            'analytics.view',
            'hierarchy.manage', 'offers.manage', 'peti.manage',
            'dealers.view', 'dealers.edit', 'customers.view',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'api']);
        }

        // Role → permission map reproduces exactly what each role can hit
        // today via routes/api.php's role: middleware — this is the
        // starting point an admin edits from the new Permissions screen,
        // not a redesign of access itself.
        $roles = [
            'super_admin'     => Permission::all()->pluck('name')->toArray(),
            'sales'           => ['crm.view', 'crm.edit', 'support.manage', 'orders.view', 'orders.create', 'analytics.view', 'hierarchy.manage', 'offers.manage', 'dealers.view', 'customers.view'],
            'warehouse_staff' => ['procurement.view', 'procurement.edit', 'inventory.view', 'inventory.export', 'bins.manage', 'qc.view', 'qc.grade', 'orders.view', 'orders.fulfill', 'logistics.manage', 'peti.manage'],
            'qc_engineer'     => ['inventory.view', 'qc.view', 'qc.grade'],
            'packing_staff'   => ['orders.view', 'inventory.view'],
            'accounts'        => ['finance.view', 'finance.edit', 'analytics.view', 'orders.view', 'customers.view'],
            'hr_manager'      => ['hr.view', 'hr.edit', 'payroll.run'],
            'b2b_partner'     => ['orders.view', 'orders.create'],
            'logistics'       => ['orders.view', 'inventory.view'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
