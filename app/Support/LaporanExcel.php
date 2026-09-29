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
     * @param  array<string, string>  $ringkasan  angka ringkas yang dicetak di atas tabel
     */
    public static function unduh(
        string $judul,
        string $periode,
        array $header,
        iterable $baris,
        string $namaBerkas,
        array $ringkasan = [],
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($judul, 0, 31));

        $kolomTerakhir = chr(ord('A') + max(0, count($header) - 1));

        $sheet->setCellValue('A1', $judul);
        $sheet->mergeCells("A1:{$kolomTerakhir}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Apotek Anugrah Husada - '.$periode);
        $sheet->mergeCells("A2:{$kolomTerakhir}2");

        // Ringkasan dicetak di atas tabel, sejajar dengan laporan Rekap dan Fast/Slow Moving.
        $barisJudulTabel = 4;
        foreach ($ringkasan as $label => $nilai) {
            $sheet->setCellValue('A'.$barisJudulTabel, $label);
            $sheet->getStyle('A'.$barisJudulTabel)->getFont()->setBold(true);
            $sheet->setCellValue('B'.$barisJudulTabel, $nilai);
            $barisJudulTabel++;
        }
        if ($ringkasan !== []) {
            $barisJudulTabel++;
        }

        $sheet->fromArray($header, null, 'A'.$barisJudulTabel);
        $sheet->getStyle("A{$barisJudulTabel}:{$kolomTerakhir}{$barisJudulTabel}")->getFont()->setBold(true);
        $sheet->getStyle("A{$barisJudulTabel}:{$kolomTerakhir}{$barisJudulTabel}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');
        $sheet->getStyle("A{$barisJudulTabel}:{$kolomTerakhir}{$barisJudulTabel}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $nomorBaris = $barisJudulTabel + 1;
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
