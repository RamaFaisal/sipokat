<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan nama peran di basis data dengan Tabel 3.2 naskah (rencana-sidang-2026-10 §3 B1,
 * keputusan K2): `super_admin` → `admin`, `staff` → `petugas`, `manajer` → `pemilik`.
 *
 * Dijalankan sebagai migrasi, bukan hanya seeder, supaya basis data yang sudah berisi data
 * riil (dan salinan `mysqldump` yang dipakai untuk deploy, lihat R2) ikut berganti nama
 * tanpa perlu seed ulang. Penugasan user tetap utuh karena `model_has_roles` menyimpan
 * `role_id`, bukan nama.
 */
return new class extends Migration
{
    private const RENAMES = [
        'super_admin' => 'admin',
        'staff' => 'petugas',
        'manajer' => 'pemilik',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /**
     * Nama tujuan yang sudah dipakai peran lain dilewati supaya migrasi tidak menabrak
     * indeks unik (nama, guard) pada basis data yang sudah sempat diperbaiki manual.
     *
     * @param  array<string, string>  $renames
     */
    private function rename(array $renames): void
    {
        foreach ($renames as $from => $to) {
            $exists = DB::table('roles')->where('name', $to)->exists();

            if ($exists) {
                continue;
            }

            DB::table('roles')->where('name', $from)->update(['name' => $to]);
        }
    }
};
