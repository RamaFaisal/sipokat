<?php

namespace App\Filament\Pages\Concerns;

use App\Support\LaporanExcel;
use App\Support\Tanggal;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Bentuk seragam halaman Laporan: filter periode, tiga aksi header yang sama, ringkasan, lalu tabel.
 *
 * Dua laporan pertama (Rekap dan Fast/Slow Moving) sudah memakai bentuk ini dan masing-masing
 * menulis sendiri aksi header beserta ekspornya. Empat laporan berikutnya memakai trait ini supaya
 * susunannya sama tanpa menyalin kode yang sama empat kali, dan supaya menambah laporan berikutnya
 * tidak berarti menebak-nebak polanya.
 *
 * Halaman yang memakainya menyediakan: properti `$rows`, metode `generate()`, dan empat keterangan
 * di bawah ini.
 */
trait LaporanSeragam
{
    /** Judul yang dicetak di berkas PDF dan Excel. */
    abstract public function judulLaporan(): string;

    /** Keterangan periode, dicetak di bawah judul. */
    abstract public function labelPeriode(): string;

    /**
     * Angka ringkas yang tampil di layar dan ikut tercetak.
     *
     * @return array<string, string>
     */
    abstract public function ringkasan(): array;

    /**
     * Judul kolom tabel ekspor.
     *
     * @return array<int, string>
     */
    abstract public function kolomEkspor(): array;

    /**
     * Isi tabel ekspor, satu array per baris, urut sesuai judul kolomnya.
     *
     * @return iterable<int, array<int, string|int|float|null>>
     */
    abstract public function barisEkspor(): iterable;

    /** Nama berkas tanpa ekstensi, misal `laporan-kedaluwarsa`. */
    abstract public function namaBerkas(): string;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tampilkan Laporan')
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->action('generate'),
            Action::make('export')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('success')
                ->action('exportExcel')
                ->visible(fn (): bool => $this->rows->isNotEmpty()),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('danger')
                ->action('exportPdf')
                ->visible(fn (): bool => $this->rows->isNotEmpty()),
        ];
    }

    public function exportExcel()
    {
        $this->generate();

        if ($this->rows->isEmpty()) {
            return null;
        }

        return LaporanExcel::unduh(
            $this->judulLaporan(),
            $this->labelPeriode(),
            $this->kolomEkspor(),
            $this->barisEkspor(),
            $this->namaBerkas().'-'.now()->format('Ymd_His').'.xlsx',
            $this->ringkasan(),
        );
    }

    public function exportPdf()
    {
        $this->generate();

        if ($this->rows->isEmpty()) {
            return null;
        }

        $pdf = Pdf::loadView('pdf.laporan', [
            'judul' => $this->judulLaporan(),
            'periode' => $this->labelPeriode(),
            'ringkasan' => $this->ringkasan(),
            'kolom' => $this->kolomEkspor(),
            'baris' => $this->barisEkspor(),
            'printedAt' => now()->translatedFormat(Tanggal::TAMPIL_JAM),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $this->namaBerkas().'-'.now()->format('Ymd_His').'.pdf',
        );
    }
}
