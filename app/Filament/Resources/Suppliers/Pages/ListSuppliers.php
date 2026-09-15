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
                ->importer(SupplierImporter::class),
            CreateAction::make()
                ->label('Tambah Supplier Obat'),
        ];
    }
}
