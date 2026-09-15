<?php

namespace Database\Seeders;

use App\Models\MedicineCategories;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Units — kunci pada alias (kode yang tercetak di faktur PBF); Flask lama diganti nama Botol.
        $units = [
            ['alias' => 'PCS', 'name' => 'Pcs'],
            ['alias' => 'STR', 'name' => 'Strip'],
            ['alias' => 'FLS', 'name' => 'Flask'],
            ['alias' => 'SCH', 'name' => 'Sachet'],
            ['alias' => 'BOX', 'name' => 'Box'],
            ['alias' => 'TUB', 'name' => 'Tube'],
            ['alias' => 'AMP', 'name' => 'Ampul'],
            ['alias' => 'KLG', 'name' => 'Kaleng'],
            ['alias' => 'TAB', 'name' => 'Tablet'],
            ['alias' => 'KPL', 'name' => 'Kaplet'],
            ['alias' => 'KAP', 'name' => 'Kapsul'],
            ['alias' => 'VIA', 'name' => 'Vial'],
            ['alias' => 'PSG', 'name' => 'Pasang'],
        ];
        foreach ($units as $unit) {
            Unit::updateOrCreate(['alias' => $unit['alias']], $unit);
        }

        // Seed Categories - 3 golongan sesuai data apotek, tidak dikelola lewat CRUD (Obat Bebas Terbatas dilebur ke Obat Bebas)
        $categories = [
            ['name' => 'Obat Bebas', 'alias' => 'OBB', 'description' => 'Obat yang dapat dibeli bebas tanpa resep dokter'],
            ['name' => 'Obat Keras', 'alias' => 'OBK', 'description' => 'Obat yang penyerahannya harus dengan resep dokter'],
            ['name' => 'Alat Kesehatan', 'alias' => 'ALK', 'description' => 'Alat kesehatan dan perbekalan non-obat'],
        ];
        foreach ($categories as $cat) {
            MedicineCategories::updateOrCreate(['name' => $cat['name']], $cat);
        }

        // PBF: lihat PbfJatengSeeder (daftar alamat PBF Dinkes Jawa Tengah).
    }
}
