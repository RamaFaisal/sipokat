<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template Excel data riil apotek (rencana-revisi-2026-09 D5, C1–C3, Bagian 8).
 *
 * Empat sheet, urutan muat: Obat → SaldoAwal (per awal periode) → Faktur (dalam periode)
 * → Penjualan (dalam periode) = saldo hari ini. Contoh baris diambil dari faktur
 * PT. Nisa Permata Mulia (contoh-data.zip) supaya formatnya jelas.
 */
class RealDataTemplate
{
    public const SHEETS = [
        'Obat' => [
            'nama' => 'Persis seperti tercetak di faktur PBF, termasuk kekuatan & merek',
            'kategori' => 'Obat Bebas / Obat Keras',
            'satuan_jual' => 'Satuan kartu stok & jual: Strip, Flask, Tube, Pcs, Sachet, Ampul, Kaleng, Box',
            'kemasan' => 'Satuan yang tertulis di faktur PBF: Box, Flask, Tube, ...',
            'isi_kemasan' => '1 kemasan = berapa satuan jual (1 bila sama)',
            'min_stock' => 'Batas waspada dalam satuan jual (kosong = bawaan: Strip 20, lainnya isi kemasan)',
        ],
        'SaldoAwal' => [
            'nama_obat' => 'Harus ada di sheet Obat',
            'batch' => 'No. batch dari fisik kemasan',
            'ed' => 'Bulan-tahun, mis. 10-2026',
            'jumlah' => 'Sisa pada AWAL periode, dalam satuan jual',
            'harga_per_satuan_jual' => 'Harga beli terakhir per satuan jual (untuk HPP)',
        ],
        'Faktur' => [
            'no_faktur' => 'Nomor faktur PBF',
            'pbf' => 'Nama PBF',
            'tanggal' => 'Tanggal terima, DD-MM-YYYY',
            'nama_obat' => 'Harus ada di sheet Obat',
            'kemasan' => 'Satuan di faktur (Box/FLS/TUBE/...)',
            'isi' => 'Isi per kemasan dalam satuan jual',
            'jumlah_kemasan' => 'Kolom Jumlah di faktur',
            'harga_per_kemasan' => 'Kolom @Harga di faktur (sudah termasuk PPN)',
            'batch' => 'Kolom No.Batch',
            'ed' => 'Kolom ED, bulan-tahun, mis. 10-2026',
        ],
        'Penjualan' => [
            'tanggal' => 'DD-MM-YYYY',
            'nama_obat' => 'Harus ada di sheet Obat',
            'jumlah' => 'Dalam satuan jual',
            'harga_jual' => 'Per satuan jual; kosong = HPP saat itu',
        ],
    ];

    public const EXAMPLES = [
        'Obat' => [
            ['CALORTUSIN KAPLET', 'Obat Keras', 'Strip', 'Box', 10, 20],
            ['LOSTACEF 125MG DRY SYR', 'Obat Keras', 'Flask', 'Flask', 1, 6],
            ['ACIFAR CR', 'Obat Bebas', 'Tube', 'Tube', 1, 5],
        ],
        'SaldoAwal' => [
            ['CALORTUSIN KAPLET', 'T10088BC', '10-2026', 35, 4100],
            ['LOSTACEF 125MG DRY SYR', '31232', '12-2026', 8, 8500],
        ],
        'Faktur' => [
            ['02028/NPM/5/24', 'PT. Nisa Permata Mulia', '11-05-2024', 'CALORTUSIN KAPLET', 'Box', 10, 10, 41000, 'T10088BC', '10-2026'],
            ['02026/NPM/5/24', 'PT. Nisa Permata Mulia', '11-05-2024', 'LOSTACEF 125MG DRY SYR', 'Flask', 1, 60, 8500, '31232', '12-2026'],
            ['02027/NPM/5/24', 'PT. Nisa Permata Mulia', '11-05-2024', 'ACIFAR CR', 'Tube', 1, 20, 5900, '40423', '03-2027'],
        ],
        'Penjualan' => [
            ['12-05-2024', 'CALORTUSIN KAPLET', 3, 6000],
            ['13-05-2024', 'LOSTACEF 125MG DRY SYR', 1, null],
        ],
    ];

    public static function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        foreach (self::SHEETS as $title => $columns) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);

            $col = 1;
            foreach ($columns as $name => $hint) {
                $sheet->setCellValue([$col, 1], $name);
                $sheet->setCellValue([$col, 2], $hint);
                $sheet->getColumnDimensionByColumn($col)->setWidth(max(18, min(48, strlen($hint) * 0.6)));
                $col++;
            }
            $sheet->getStyle([1, 1, count($columns), 1])->getFont()->setBold(true);
            $sheet->getStyle([1, 1, count($columns), 1])->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
            $sheet->getStyle([1, 2, count($columns), 2])->getFont()->setItalic(true)->getColor()->setRGB('666666');

            $row = 3;
            foreach (self::EXAMPLES[$title] ?? [] as $example) {
                $sheet->fromArray($example, null, 'A'.$row);
                $row++;
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public static function write(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        (new Xlsx(self::build()))->save($path);
    }
}
