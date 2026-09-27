<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Peran (Tabel 3.2), akun awal, master data (kategori, satuan, PBF, kriteria SAW), obat riil, faktur
     * riil (RO), contoh PO, dan simulasi penjualan supaya `db:seed` di deploy manapun
     * membuat tiap menu langsung terisi (rencana-sidang-2026-10 §3). Urutan menjaga
     * dependensi: obat dulu sebelum RO/PO/penjualan yang mengacunya. Data demo sintetis
     * (`SpkTestDataSeeder`, 150 obat contoh) sengaja **tidak** dipanggil di sini beda
     * dari data riil di atas, dan akan bentrok bila dijalankan bersamaan.
     * Tanpa WithoutModelEvents: hook `creating` Medicine (kode OBT####) harus tetap jalan.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            MasterDataSeeder::class,
            PbfJatengSeeder::class,
            SawCriteriaSeeder::class,
            MedicineDataSeeder::class,
            FakturNpmSeeder::class,
            PurchaseOrderSeeder::class,
            SimulasiPenjualanSeeder::class,
        ]);
    }
}
