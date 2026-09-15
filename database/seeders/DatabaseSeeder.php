<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Akun awal, master data (kategori, satuan, PBF), dan kriteria SAW.
     * Data demo obat & transaksi: `php artisan db:seed --class=SpkTestDataSeeder`
     * (sengaja terpisah supaya basis produksi tidak terisi data contoh).
     * Tanpa WithoutModelEvents: hook `creating` Medicine (kode OBT####) harus tetap jalan.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            MasterDataSeeder::class,
            PbfJatengSeeder::class,
            SawCriteriaSeeder::class,
        ]);
    }
}
