<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@shf.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Admin@123'),
                'role' => 'super_admin',
                'is_active' => true,
                'phone' => '+91 99747 89089',
            ]
        );
        User::updateOrCreate(
            ['email' => 'denish@shfworld.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Denish@123'),
                'role' => 'admin',
                'is_active' => true,
                'phone' => '+91 99747 89089',
            ]
        );
    }
}
