<?php

namespace App\Support;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Menjalankan Importer Filament atas berkas Excel, bukan hanya CSV.
 *
 * `ImportAction` bawaan Filament membaca berkasnya dengan league/csv, jadi .xlsx tidak bisa dipakai
 * betapapun tipe berkas yang diterima disetel. Yang diganti di sini hanya pembacanya: berkas dibaca
 * PhpSpreadsheet (sudah dipakai `RealDataImporter`), lalu tiap baris diserahkan ke kelas Importer
 * yang sudah ada. Dengan begitu tebakan nama kolom, aturan validasi, dan pengenalan data lama lewat
 * `resolveRecord()` tetap satu sumber, tidak ditulis dua kali.
 *
 * CSV ikut terbaca karena PhpSpreadsheet juga membacanya, jadi berkas lama tetap bisa dipakai.
 */
class SpreadsheetImporter
{
    /** Tipe berkas yang diterima kotak unggah. */
    public const TIPE = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
    ];

    /**
     * @param  class-string<Importer>  $importerClass
     * @return array{berhasil: int, gagal: int, pesan: array<int, string>}
     */
    public static function jalankan(string $importerClass, string $path, string $namaBerkas): array
    {
        [$header, $baris] = self::baca($path);

        if ($header === []) {
            return ['berhasil' => 0, 'gagal' => 0, 'pesan' => ['Berkas kosong atau tidak ada baris judul kolom.']];
        }

        [$peta, $hilang] = self::petakanKolom($importerClass, $header);

        if ($hilang !== []) {
            return [
                'berhasil' => 0,
                'gagal' => count($baris),
                'pesan' => ['Kolom wajib tidak ditemukan di berkas: '.implode(', ', $hilang).'. Unduh template untuk melihat susunan kolomnya.'],
            ];
        }

        $import = Import::create([
            'file_name' => $namaBerkas,
            'file_path' => $namaBerkas,
            'importer' => $importerClass,
            'total_rows' => count($baris),
            'user_id' => auth()->id(),
        ]);

        $importer = new $importerClass($import, $peta, []);

        $berhasil = 0;
        $pesan = [];

        foreach ($baris as $nomor => $isi) {
            try {
                $importer($isi);
                $berhasil++;
            } catch (ValidationException $e) {
                // Nomor baris dihitung seperti yang terlihat di Excel, jadi bisa langsung dibuka.
                $pesan[] = 'Baris '.$nomor.': '.collect($e->errors())->flatten()->implode(' ');
            } catch (Throwable $e) {
                $pesan[] = 'Baris '.$nomor.': '.$e->getMessage();
            }
        }

        $import->update([
            'processed_rows' => count($baris),
            'successful_rows' => $berhasil,
            'completed_at' => now(),
        ]);

        return [
            'berhasil' => $berhasil,
            'gagal' => count($baris) - $berhasil,
            // Pesan dibatasi supaya notifikasinya tetap terbaca; sisanya cukup dihitung.
            'pesan' => array_slice($pesan, 0, 5),
        ];
    }

    /**
     * Baris judul kolom dan baris isinya, keyed nomor baris seperti di Excel.
     *
     * @return array{0: array<int, string>, 1: array<int, array<string, mixed>>}
     */
    private static function baca(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);

        $header = [];
        $barisHeader = 0;

        foreach ($sheet as $nomor => $isi) {
            if (self::kosong($isi)) {
                continue;
            }

            $header = array_map(fn ($nilai): string => trim((string) $nilai), $isi);
            $barisHeader = $nomor;
            break;
        }

        if ($header === []) {
            return [[], []];
        }

        $baris = [];

        foreach ($sheet as $nomor => $isi) {
            if ($nomor <= $barisHeader || self::kosong($isi)) {
                continue;
            }

            $data = [];

            foreach ($header as $kolom => $judul) {
                if ($judul === '') {
                    continue;
                }

                $nilai = $isi[$kolom] ?? null;
                $data[$judul] = is_string($nilai) ? trim($nilai) : $nilai;
            }

            $baris[$nomor + 1] = $data;
        }

        return [$header, $baris];
    }

    /**
     * @param  array<int, mixed>  $isi
     */
    private static function kosong(array $isi): bool
    {
        return collect($isi)->filter(fn ($nilai): bool => filled($nilai))->isEmpty();
    }

    /**
     * Pasangan nama kolom importer dengan judul kolom di berkas, memakai daftar tebakan yang sudah
     * ada di tiap `ImportColumn`.
     *
     * @param  class-string<Importer>  $importerClass
     * @param  array<int, string>  $header
     * @return array{0: array<string, string|null>, 1: array<int, string>} peta kolom, kolom wajib yang tidak ketemu
     */
    private static function petakanKolom(string $importerClass, array $header): array
    {
        $peta = [];
        $hilang = [];

        foreach ($importerClass::getColumns() as $kolom) {
            $tebakan = $kolom->getGuesses();
            $cocok = null;

            foreach ($header as $judul) {
                if ($judul === '') {
                    continue;
                }

                // getGuesses() sudah mengembalikan bentuk huruf kecil beserta variannya.
                if (in_array(Str::lower($judul), $tebakan, true)) {
                    $cocok = $judul;
                    break;
                }
            }

            $peta[$kolom->getName()] = $cocok;

            if ($cocok === null && $kolom->isMappingRequired()) {
                $hilang[] = $kolom->getLabel() ?? $kolom->getName();
            }
        }

        return [$peta, $hilang];
    }
}
