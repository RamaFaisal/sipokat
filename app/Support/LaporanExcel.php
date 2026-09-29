<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penulis Excel ringkas untuk laporan baru (K7).
 *
 * Dua laporan lama punya penulisnya sendiri yang menata banyak blok ringkasan; empat laporan baru
 * bentuknya sederhana (judul, periode, satu tabel), jadi cukup satu penulis bersama daripada
 * menyalin seratus baris penataan ke tiap laporan.
 */
class LaporanExcel
{
    /**
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, string|int|float|null>>  $baris
     */
    public static function unduh(string $judul, string $periode, array $header, iterable $baris, string $namaBerkas): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($judul, 0, 31));

        $kolomTerakhir = chr(ord('A') + max(0, count($header) - 1));

        $sheet->setCellValue('A1', $judul);
        $sheet->mergeCells("A1:{$kolomTerakhir}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Apotek Anugrah Husada - '.$periode);
        $sheet->mergeCells("A2:{$kolomTerakhir}2");

        $sheet->fromArray($header, null, 'A4');
        $sheet->getStyle("A4:{$kolomTerakhir}4")->getFont()->setBold(true);
        $sheet->getStyle("A4:{$kolomTerakhir}4")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->getStyle("A4:{$kolomTerakhir}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $nomorBaris = 5;
        foreach ($baris as $isi) {
            $sheet->fromArray(array_values($isi), null, 'A'.$nomorBaris);
            $nomorBaris++;
        }

        foreach (range('A', $kolomTerakhir) as $kolom) {
            $sheet->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $namaBerkas);
    }
}
