<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tiga peran sesuai Tabel 3.2 naskah (rencana-sidang-2026-10 §3 B1):
 *
 * - `admin` Admin: akses penuh (semua permission Shield).
 * - `petugas` Petugas Apotek: transaksi harian (PO/RO, penjualan, opname), master
 * obat/satuan/PBF hanya baca, menjalankan perhitungan SPK, lihat laporan.
 * - `pemilik` Pemilik atau Manajer: monitoring baca-saja (dashboard, SAW, laporan, data
 * inventory) tanpa satu pun izin tulis.
 *
 * Nama permission mengikuti `config/filament-shield.php` (pemisah `:`, huruf Pascal),
 * mis. `ViewAny:Medicine`, `View:LaporanRekap`. Permission dibuat oleh `shield:generate`;
 * seeder ini memanggilnya lebih dulu supaya `db:seed` di server baru tetap utuh tanpa
 * langkah manual (hanya bagian permission, berkas policy sudah ikut ter-commit).
 *
 * Idempoten: `syncPermissions` menulis ulang matriks, aman dijalankan berkali-kali.
 */
class RoleSeeder extends Seeder
{
    public const ADMIN = 'admin';

    public const PETUGAS = 'petugas';

    public const PEMILIK = 'pemilik';

    /** Resource yang boleh dibaca dan ditulis penuh oleh Petugas. */
    private const WRITE = ['ViewAny', 'View', 'Create', 'Update', 'Delete'];

    /** Hanya melihat. */
    private const READ = ['ViewAny', 'View'];

    public function run(): void
    {
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->sync(self::ADMIN, Permission::pluck('name')->all());
        $this->sync(self::PETUGAS, $this->petugasPermissions());
        $this->sync(self::PEMILIK, $this->pemilikPermissions());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Petugas Apotek: "mengelola data obat masuk dan obat keluar, melihat stok dan masa
     * kedaluwarsa, serta menjalankan proses perhitungan SPK" (Tabel 3.2). Master obat,
     * satuan, dan PBF sengaja hanya baca master adalah wewenang Admin; Petugas tetap
     * perlu melihatnya untuk mengisi baris PO/RO/penjualan. `Delete` pada dokumen
     * transaksi wajib ada: penjualan tidak bisa diedit, koreksinya hapus lalu buat ulang
     * (aturan S1), dan penghapusan itu yang membalikkan ledger lewat StockMovementService.
     */
    private function petugasPermissions(): array
    {
        return array_merge(
            $this->for(self::WRITE, ['PurchaseOrder', 'ReceiveOrder', 'Order', 'MedicineStockOpname']),
            $this->for(self::READ, ['Medicine', 'Unit', 'Supplier', 'MedicineStock', 'SawCriteria']),
            $this->pages(),
            $this->widgets(),
        );
    }

    /**
     * Pemilik atau Manajer: "melihat laporan inventory, hasil rekomendasi SPK, dan
     * informasi pendukung keputusan restock" (Tabel 3.2). Tidak ada izin tulis sama
     * sekali: "Buat PO" dari ranking disembunyikan oleh guard di halaman SPK. Memuat
     * ulang peringkat boleh, karena sejak snapshot dihapus perhitungan tidak menulis apa pun.
     */
    private function pemilikPermissions(): array
    {
        return array_merge(
            $this->for(self::READ, [
                'Medicine', 'Unit', 'Supplier', 'MedicineStock', 'MedicineStockOpname',
                'PurchaseOrder', 'ReceiveOrder', 'Order', 'SawCriteria',
            ]),
            $this->pages(),
            $this->widgets(),
        );
    }

    /**
     * Halaman kustom yang dipakai kedua peran non-Admin. `View:ManageGeneralSettings`
     * (PPN, identitas apotek) sengaja tidak masuk itu milik Admin.
     */
    private function pages(): array
    {
        return [
            'View:Dashboard',
            'View:MedicineStockDetail',
            'View:LaporanRekap',
            'View:LaporanKedaluwarsa',
            'View:LaporanStokObat',
            'View:LaporanPembelianPbf',
            'View:LaporanOpname',
            'View:LaporanMoving',
            // Halaman peringkat SAW. Sejak snapshot dihapus (2026-09-27) membukanya tidak
            // menulis apa pun, jadi cukup izin baca termasuk untuk Pemilik.
            'View:SawCalculation',
        ];
    }

    private function widgets(): array
    {
        return [
            'View:RingkasanStatWidget',
            'View:SawTop10RestockWidget',
            'View:LowStockMedicinesWidget',
            'View:ExpiringMedicinesWidget',
            'View:SalesSummaryWidget',
        ];
    }

    /**
     * @param  list<string>  $prefixes
     * @param  list<string>  $subjects
     * @return list<string>
     */
    private function for(array $prefixes, array $subjects): array
    {
        $names = [];
        foreach ($subjects as $subject) {
            foreach ($prefixes as $prefix) {
                $names[] = $prefix.':'.$subject;
            }
        }

        return $names;
    }

    /**
     * Permission yang belum ada di basis data dilewati diam-diam (mis. halaman dihapus
     * di revisi berikutnya) supaya seeder tidak menggagalkan seluruh `db:seed`.
     *
     * @param  list<string>  $permissions
     */
    private function sync(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $existing = Permission::whereIn('name', array_unique($permissions))->pluck('name')->all();

        if (($missing = array_diff(array_unique($permissions), $existing)) !== []) {
            $this->command?->warn("RoleSeeder [{$roleName}]: permission tidak ditemukan, dilewati: ".implode(', ', $missing));
        }

        $role->syncPermissions($existing);
    }
}
