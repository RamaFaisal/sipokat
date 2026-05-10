<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImporterTemplate
{
    /**
     * Generate XLSX template file dari Filament Importer columns,
     * berisi 1 baris header (label) + 1 baris contoh data.
     *
     * @param  class-string<\Filament\Actions\Imports\Importer>  $importerClass
     */
    public static function xlsx(string $importerClass, string $filename): StreamedResponse
    {
        $columns = $importerClass::getColumns();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        foreach ($columns as $index => $column) {
            $col = Coordinate::stringFromColumnIndex($index + 1);

            $sheet->setCellValue("{$col}1", $column->getExampleHeader());

            $example = $column->getExamples()[0] ?? null;
            if ($example !== null && $example !== '') {
                $sheet->setCellValueExplicit("{$col}2", (string) $example, DataType::TYPE_STRING);
            }

            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($columns));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Generate CSV template file dari Filament Importer columns,
     * berisi 1 baris header (label) + 1 baris contoh data.
     * UTF-8 BOM disertakan agar Excel buka tanpa salah encoding.
     *
     * @param  class-string<\Filament\Actions\Imports\Importer>  $importerClass
     */
    public static function csv(string $importerClass, string $filename): StreamedResponse
    {
        $columns = $importerClass::getColumns();

        $headers = array_map(fn ($column) => $column->getExampleHeader(), $columns);
        $examples = array_map(fn ($column) => (string) ($column->getExamples()[0] ?? ''), $columns);

        return response()->streamDownload(function () use ($headers, $examples): void {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, escape: '');
            fputcsv($handle, $examples, escape: '');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
