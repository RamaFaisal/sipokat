<?php

namespace Database\Seeders;

use App\Models\SawCriteria;
use Illuminate\Database\Seeder;

/**
 * Empat kriteria SAW dengan ambang hasil wawancara Apotek Anugrah Husada
 * (docs/update-dari-wawancara.md §3; rencana-revisi-2026-09 §7.2).
 *
 * Semua aturan skala INKLUSIF (B2). C1 dinyatakan sebagai rasio stok ÷ batas minimum,
 * diturunkan dari kolom wawancara (≤20 / 21–40 / 41–70 / 71–100 / ≥101) dibagi batas
 * waspada 20 — dua desimal tanpa celah karena rasio dibulatkan dua desimal (K14).
 * Bobot 0,30 / 0,30 / 0,20 / 0,20 terkonfirmasi lapangan (§2).
 */
class SawCriteriaSeeder extends Seeder
{
    public function run(): void
    {
        $criteria = [
            [
                'code' => 'C1',
                'name' => 'Rasio Stok',
                'type' => 'cost',
                'weight' => 0.300,
                'sort_order' => 1,
                'description' => 'Stok tersedia (belum kedaluwarsa) dibagi batas minimum obat. ≤1 berarti sudah di bawah/sama batas waspada. Semakin kecil semakin prioritas (cost, normalisasi min/X).',
                'scale_rules' => [
                    ['min' => null, 'max' => 1.00, 'score' => 1],
                    ['min' => 1.01, 'max' => 2.00, 'score' => 2],
                    ['min' => 2.01, 'max' => 3.50, 'score' => 3],
                    ['min' => 3.51, 'max' => 5.00, 'score' => 4],
                    ['min' => 5.01, 'max' => null, 'score' => 5],
                ],
            ],
            [
                'code' => 'C2',
                'name' => 'Permintaan',
                'type' => 'benefit',
                'weight' => 0.300,
                'sort_order' => 2,
                'description' => 'Permintaan per bulan (satuan jual), diproyeksikan ke 30 hari dari periode yang dipilih. Semakin tinggi semakin prioritas (benefit, normalisasi X/max).',
                'scale_rules' => [
                    ['min' => null, 'max' => 10,   'score' => 1],
                    ['min' => 11,   'max' => 30,   'score' => 2],
                    ['min' => 31,   'max' => 65,   'score' => 3],
                    ['min' => 66,   'max' => 100,  'score' => 4],
                    ['min' => 101,  'max' => null, 'score' => 5],
                ],
            ],
            [
                'code' => 'C3',
                'name' => 'Sisa Kedaluwarsa',
                'type' => 'cost',
                'weight' => 0.200,
                'sort_order' => 3,
                'description' => 'Sisa hari ke ED batch terjauh yang masih bersisa (horizon stok yang akan tersisa setelah FEFO). Stok tersedia 0 dihitung 0 hari. Batas 90 hari = tenggat retur ke PBF. Semakin dekat semakin prioritas (cost).',
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
                'name' => 'Harga Pokok',
                'type' => 'cost',
                'weight' => 0.200,
                'sort_order' => 4,
                'description' => 'HPP rata-rata bergerak per satuan jual (Rp) dari kartu stok. Semakin murah semakin prioritas (cost). Ambang wawancara: ≤2.000 murah, >100.000 mahal.',
                'scale_rules' => [
                    ['min' => null,   'max' => 2000,   'score' => 1],
                    ['min' => 2001,   'max' => 10000,  'score' => 2],
                    ['min' => 10001,  'max' => 50000,  'score' => 3],
                    ['min' => 50001,  'max' => 100000, 'score' => 4],
                    ['min' => 100001, 'max' => null,   'score' => 5],
                ],
            ],
        ];

        foreach ($criteria as $data) {
            SawCriteria::updateOrCreate(['code' => $data['code']], $data + ['is_active' => true]);
        }
    }
}
