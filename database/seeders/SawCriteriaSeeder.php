<?php

namespace Database\Seeders;

use App\Models\SawCriteria;
use Illuminate\Database\Seeder;

class SawCriteriaSeeder extends Seeder
{
    /**
     * Default 4 kriteria SAW sesuai Tabel 3.4-3.8 proposal TA.
     * Skala konversi nilai mentah ke 1-5 disimpan di scale_rules (editable admin).
     */
    public function run(): void
    {
        $criteria = [
            [
                'code' => 'C1',
                'name' => 'Stok',
                'type' => 'cost',
                'weight' => 0.300,
                'sort_order' => 1,
                'description' => 'Jumlah stok obat saat ini (strip/botol). Semakin sedikit semakin prioritas (cost). Skor disusun arah natural; prioritas diterapkan lewat rumus normalisasi min/X.',
                'scale_rules' => [
                    ['min' => null, 'max' => 10,   'score' => 1],
                    ['min' => 11,   'max' => 30,   'score' => 2],
                    ['min' => 31,   'max' => 60,   'score' => 3],
                    ['min' => 61,   'max' => 100,  'score' => 4],
                    ['min' => 101,  'max' => null, 'score' => 5],
                ],
            ],
            [
                'code' => 'C2',
                'name' => 'Permintaan',
                'type' => 'benefit',
                'weight' => 0.300,
                'sort_order' => 2,
                'description' => 'Jumlah permintaan/penjualan obat per bulan. Semakin tinggi semakin prioritas.',
                'scale_rules' => [
                    ['min' => 81,   'max' => null, 'score' => 5],
                    ['min' => 61,   'max' => 80,   'score' => 4],
                    ['min' => 41,   'max' => 60,   'score' => 3],
                    ['min' => 20,   'max' => 40,   'score' => 2],
                    ['min' => null, 'max' => 19,   'score' => 1],
                ],
            ],
            [
                'code' => 'C3',
                'name' => 'Sisa Kedaluwarsa',
                'type' => 'cost',
                'weight' => 0.200,
                'sort_order' => 3,
                'description' => 'Sisa hari sampai obat kedaluwarsa (FEFO batch terdekat). Semakin dekat semakin prioritas (cost). Skor disusun arah natural; prioritas diterapkan lewat rumus normalisasi min/X.',
                'scale_rules' => [
                    ['min' => null, 'max' => 90,   'score' => 1],
                    ['min' => 91,   'max' => 180,  'score' => 2],
                    ['min' => 181,  'max' => 365,  'score' => 3],
                    ['min' => 366,  'max' => 730,  'score' => 4],
                    ['min' => 731,  'max' => null, 'score' => 5],
                ],
            ],
            [
                'code' => 'C4',
                'name' => 'Harga Beli',
                'type' => 'cost',
                'weight' => 0.200,
                'sort_order' => 4,
                'description' => 'Harga beli obat per unit (Rp). Semakin murah semakin prioritas (cost). Skor disusun arah natural; prioritas diterapkan lewat rumus normalisasi min/X.',
                'scale_rules' => [
                    ['min' => null,   'max' => 10000,  'score' => 1],
                    ['min' => 10001,  'max' => 25000,  'score' => 2],
                    ['min' => 25001,  'max' => 50000,  'score' => 3],
                    ['min' => 50001,  'max' => 100000, 'score' => 4],
                    ['min' => 100001, 'max' => null,   'score' => 5],
                ],
            ],
        ];

        foreach ($criteria as $data) {
            SawCriteria::updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
