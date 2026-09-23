<?php

namespace Database\Seeders;

use App\Services\RealDataImporter;
use Illuminate\Database\Seeder;

/**
 * 127 obat riil Apotek Anugrah Husada (rencana-revisi-2026-09 D5). Sumbernya salinan
 * `storage/app/import/master-data-obat.csv` (berkas lokal, tidak ikut git
 * lihat storage/app/.gitignore) di `database/seeders/data`, supaya master data ikut
 * termuat lewat `db:seed` di server manapun, bukan bergantung berkas yang harus
 * disalin manual. Aman dijalankan berulang (upsert berdasar nama ternormalisasi, M6).
 */
class MedicineDataSeeder extends Seeder
{
    public function run(RealDataImporter $importer): void
    {
        $path = database_path('seeders/data/master-data-obat.csv');

        if (! is_file($path)) {
            $this->command?->warn("MedicineDataSeeder: berkas {$path} tidak ada, dilewati.");

            return;
        }

        $rows = [];
        $handle = fopen($path, 'r');
        fgetcsv($handle); // header: Nama Obat,Kategori,Satuan Jual,Kemasan Pembelian,Isi Per Kemasan,Stok Minimum
        $rowNumber = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count(array_filter($data, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $rows[] = [
                'nama' => $data[0] ?? '',
                'kategori' => $data[1] ?? '',
                'satuan_jual' => $data[2] ?? '',
                'kemasan' => $data[3] ?? '',
                'isi_kemasan' => $data[4] ?? '',
                'min_stock' => $data[5] ?? '',
                '_row' => $rowNumber,
            ];
        }
        fclose($handle);

        $result = $importer->importMedicinesOnly($rows);

        if ($result['errors']) {
            foreach ($result['errors'] as $error) {
                $this->command?->error($error);
            }

            throw new \RuntimeException('MedicineDataSeeder: '.count($result['errors']).' baris gagal diimpor lihat pesan di atas.');
        }

        $this->command?->info("MedicineDataSeeder: {$result['summary']['obat']} obat termuat/diperbarui.");
    }
}
