<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Imports\SupplierImporter;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Support\ImporterTemplate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = "Supplier Obat";

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->label('Import Supplier')
                ->importer(SupplierImporter::class)
                ->extraModalFooterActions([
                    Action::make('downloadXlsxTemplate')
                        ->label('Unduh contoh berkas XLSX')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->action(fn () => ImporterTemplate::xlsx(
                            SupplierImporter::class,
                            'template-import-supplier.xlsx'
                        )),
                ]),
            CreateAction::make()
                ->label('Tambah Supplier Obat'),
        ];
    }
}
