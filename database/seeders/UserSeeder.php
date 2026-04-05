<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
        $staffRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'staff']);
        $manajerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'manajer']);

        $adminUser = \App\Models\User::firstOrCreate(
            ['email' => 'adminsipokat@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole($adminRole);

        $staffUser = \App\Models\User::firstOrCreate(
            ['email' => 'staffsipokat@gmail.com'],
            [
                'name' => 'Staff',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        $staffUser->assignRole($staffRole);

        $manajerUser = \App\Models\User::firstOrCreate(
            ['email' => 'manajersipokat@gmail.com'],
            [
                'name' => 'Manajer',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
        $manajerUser->assignRole($manajerRole);
    }
}
