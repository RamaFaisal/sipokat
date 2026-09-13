<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\MedicineCategories;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Units
        $units = [
            ['name' => 'Pcs', 'alias' => 'PCS'],
            ['name' => 'Strip', 'alias' => 'STR'],
            ['name' => 'Flask', 'alias' => 'FLS'],
            ['name' => 'Sachet', 'alias' => 'SCH'],
            ['name' => 'Box', 'alias' => 'BOX'],
            ['name' => 'Tube', 'alias' => 'TUB'],
            ['name' => 'Ampul', 'alias' => 'AMP'],
            ['name' => 'Kaleng', 'alias' => 'KLG'],
        ];
        foreach ($units as $unit) {
            Unit::updateOrCreate(['name' => $unit['name']], $unit);
        }

        // Seed Categories - dikunci dua golongan, tidak lagi dikelola lewat CRUD
        $categories = [
            ['name' => 'Obat Bebas', 'alias' => 'OBB', 'description' => 'Obat yang dapat dibeli bebas tanpa resep dokter'],
            ['name' => 'Obat Keras', 'alias' => 'OBK', 'description' => 'Obat yang penyerahannya harus dengan resep dokter'],
        ];
        foreach ($categories as $cat) {
            MedicineCategories::updateOrCreate(['name' => $cat['name']], $cat);
        }

        // Seed Suppliers
        $suppliers = [
            [
                'code' => 'SUP001',
                'name' => 'Kimia Farma TD',
                'address' => 'Jakarta',
                'phone' => '021-123456',
                'email' => 'contact@kimiafarma.id',
                'pic' => 'Budi',
                'status' => 'active'
            ],
            [
                'code' => 'SUP002',
                'name' => 'Enseval',
                'address' => 'Bekasi',
                'phone' => '021-987654',
                'email' => 'info@enseval.com',
                'pic' => 'Siti',
                'status' => 'active'
            ],
        ];
        foreach ($suppliers as $sup) {
            Supplier::updateOrCreate(['name' => $sup['name']], $sup);
        }
    }
}
