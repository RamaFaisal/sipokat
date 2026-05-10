<?php

namespace App\Filament\Resources\Medicines\Pages;

use App\Filament\Imports\MedicineImporter;
use App\Filament\Resources\Medicines\MedicineResource;
use App\Support\ImporterTemplate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicines extends ListRecords
{
    protected static string $resource = MedicineResource::class;

    protected static ?string $title = "Obat";

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import Obat')
                ->importer(MedicineImporter::class)
                ->extraModalFooterActions([
                    Action::make('downloadXlsxTemplate')
                        ->label('Unduh contoh berkas XLSX')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(fn () => ImporterTemplate::xlsx(
                            MedicineImporter::class,
                            'template-import-obat.xlsx'
                        )),
                ]),
            CreateAction::make()
                ->label('Tambah Obat'),
        ];
    }
}
