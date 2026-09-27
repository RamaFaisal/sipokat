<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Satu akun contoh per peran Tabel 3.2, dipakai untuk pengujian blackbox (B3) dan demo
 * sidang: login bergantian untuk memperlihatkan menu yang berbeda tiap peran. Matriks
 * izinnya ada di RoleSeeder yang harus sudah jalan lebih dulu (lihat DatabaseSeeder).
 */
class UserSeeder extends Seeder
{
    private const ACCOUNTS = [
        ['Admin', 'adminsipokat@gmail.com', RoleSeeder::ADMIN],
        ['Petugas Apotek', 'staffsipokat@gmail.com', RoleSeeder::PETUGAS],
        ['Pemilik Apotek', 'manajersipokat@gmail.com', RoleSeeder::PEMILIK],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'email_verified_at' => now(),
                ]
            );

            // Nama tampilan disamakan dengan istilah Tabel 3.2 juga pada basis data lama
            // ("Staff" → "Petugas Apotek"), tetapi kata sandi tidak pernah ditimpa.
            if ($user->name !== $name) {
                $user->update(['name' => $name]);
            }

            // syncRoles, bukan assignRole: basis data lama memakai super_admin/staff/manajer
            // yang sudah diganti namanya oleh migrasi, dan peran ganda tidak dikehendaki.
            $user->syncRoles([$role]);
        }
    }
}
