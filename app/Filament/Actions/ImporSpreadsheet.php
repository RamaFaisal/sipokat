<?php

namespace App\Filament\Actions;

use App\Support\ImporterTemplate;
use App\Support\SpreadsheetImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Tombol import master data yang menerima Excel maupun CSV.
 *
 * Menggantikan `ImportAction` bawaan Filament yang hanya bisa membaca CSV. Yang hilang dibanding
 * aksi bawaan: modal pemetaan kolom, antrean, dan unduhan baris gagal. Gantinya kolom ditebak
 * otomatis dari judulnya, dan bila kolom wajib tidak ketemu, pesannya menyebut kolom mana sekaligus
 * mengarahkan ke template.
 *
 * Unduhan template berada di dalam modalnya, bukan tombol tersendiri di header, mengikuti cara
 * Filament menaruh tombol contoh CSV pada `ImportAction`: tempat orang mencarinya adalah saat
 * modalnya sudah terbuka dan ternyata belum punya berkasnya.
 */
class ImporSpreadsheet
{
    /**
     * @param  class-string<\Filament\Actions\Imports\Importer>  $importerClass
     * @param  string  $entitas  dipakai untuk label tombol, misal "Obat"
     */
    public static function aksi(string $importerClass, string $entitas, string $namaBerkas): Action
    {
        return Action::make('impor')
            ->label('Import '.$entitas)
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->modalHeading('Import '.$entitas)
            ->modalSubmitActionLabel('Proses')
            ->registerModalActions([
                Action::make('unduhTemplate')
                    ->label('Unduh Template')
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->link()
                    ->action(fn () => ImporterTemplate::xlsx($importerClass, $namaBerkas.'.xlsx')),
            ])
            ->modalDescription(fn (Action $action): Htmlable => new HtmlString(
                '<p>Berkas Excel (.xlsx, .xls) atau CSV. Baris pertama berisi judul kolom, seperti pada template.</p></br>'
                .$action->getModalAction('unduhTemplate')?->toHtml()
            ))
            ->schema([
                FileUpload::make('berkas')
                    ->label('Berkas')
                    ->acceptedFileTypes(SpreadsheetImporter::TIPE)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->action(function (array $data) use ($importerClass): void {
                $berkas = $data['berkas'];
                $asli = $berkas->getClientOriginalName();

                // Disalin dulu dengan ekstensi aslinya: berkas sementara Livewire tidak selalu
                // menyimpannya, sedangkan PhpSpreadsheet memilih pembaca dari ekstensi itu.
                $ekstensi = strtolower(pathinfo($asli, PATHINFO_EXTENSION)) ?: 'xlsx';
                $sementara = tempnam(sys_get_temp_dir(), 'impor').'.'.$ekstensi;
                copy($berkas->getRealPath(), $sementara);

                try {
                    $hasil = SpreadsheetImporter::jalankan($importerClass, $sementara, $asli);
                } finally {
                    @unlink($sementara);
                }

                $badan = number_format($hasil['berhasil'], 0, ',', '.').' baris berhasil, '
                    .number_format($hasil['gagal'], 0, ',', '.').' baris gagal.';

                if ($hasil['pesan'] !== []) {
                    $badan .= ' '.implode(' ', $hasil['pesan']);
                }

                Notification::make()
                    ->title($hasil['gagal'] === 0 ? 'Import selesai' : 'Import selesai sebagian')
                    ->body($badan)
                    ->status($hasil['gagal'] === 0 ? 'success' : 'warning')
                    ->send();
            });
    }
}
