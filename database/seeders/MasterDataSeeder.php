<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\MedicineCategories;
use App\Models\MedicineRack;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Units
        $units = [
            ['name' => 'Tablet', 'alias' => 'TAB'],
            ['name' => 'Kapsul', 'alias' => 'KAP'],
            ['name' => 'Sirup', 'alias' => 'SYR'],
            ['name' => 'Botol', 'alias' => 'BTL'],
            ['name' => 'Salep', 'alias' => 'SLP'],
        ];
        foreach ($units as $unit) {
            Unit::updateOrCreate(['name' => $unit['name']], $unit);
        }

        // Seed Categories
        $categories = [
            ['name' => 'Analisik', 'alias' => 'ANA', 'description' => 'Pereda nyeri'],
            ['name' => 'Antibiotik', 'alias' => 'ANT', 'description' => 'Melawan bakteri'],
            ['name' => 'Antiseptik', 'alias' => 'ASP', 'description' => 'Pembersih luka'],
            ['name' => 'Vitamin', 'alias' => 'VIT', 'description' => 'Suplemen tubuh'],
        ];
        foreach ($categories as $cat) {
            MedicineCategories::updateOrCreate(['name' => $cat['name']], $cat);
        }

        // Seed Racks
        $racks = [
            ['name' => 'Rak A1', 'description' => 'Obat Umum'],
            ['name' => 'Rak A2', 'description' => 'Obat Keras'],
            ['name' => 'Lemari Es', 'description' => 'Suhu Dingin'],
        ];
        foreach ($racks as $rack) {
            MedicineRack::updateOrCreate(['name' => $rack['name']], $rack);
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
