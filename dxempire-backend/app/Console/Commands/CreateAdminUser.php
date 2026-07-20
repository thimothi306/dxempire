<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
                            {--name= : Full name}
                            {--email= : Email address}
                            {--phone= : Phone number}
                            {--password= : Password}
                            {--role=super_admin : Role (super_admin, sales, warehouse_staff, accounts, hr_manager, qc_engineer)}';

    protected $description = 'Create a staff/admin user with email+password login';

    public function handle(): int
    {
        $name     = $this->option('name')     ?? $this->ask('Full name');
        $email    = $this->option('email')    ?? $this->ask('Email address');
        $phone    = $this->option('phone')    ?? $this->ask('Phone number');
        $password = $this->option('password') ?? $this->secret('Password');
        $role     = $this->option('role');

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return self::FAILURE;
        }

        $user = User::create([
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'password'  => Hash::make($password),
            'role'      => $role,
            'is_active' => true,
        ]);

        $user->assignRole($role);

        $this->info("Admin user created successfully.");
        $this->table(['Field', 'Value'], [
            ['Name',  $user->name],
            ['Email', $user->email],
            ['Phone', $user->phone],
            ['Role',  $user->role],
        ]);

        return self::SUCCESS;
    }
}
