<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $admin = User::firstOrCreate(
            ['phone' => '9999999999'],
            [
                'name'      => 'Super Admin',
                'email'     => 'admin@dxempire.com',
                'role'      => 'super_admin',
                'is_active' => true,
            ]
        );

        $admin->assignRole('super_admin');
    }
}
