<?php

namespace App\Services;

use App\Models\User;

class UniqueCodeGenerator
{
    public static function generateForRole($role): string
    {
        $prefixes = [
            'super_admin'      => 'SA',
            'sales'            => 'SM',
            'district_manager' => 'DM',
            'area_manager'     => 'AM',
            'sales_guy'        => 'SG',
            'warehouse_staff'  => 'WH',
            'qc_engineer'      => 'QC',
            'accounts'         => 'ACC',
            'hr_manager'       => 'HR',
            'logistics'        => 'LOG',
        ];

        $prefix = $prefixes[$role] ?? 'USR';
        $count = User::where('unique_code', 'LIKE', "$prefix%")->count() + 1;

        return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
}
