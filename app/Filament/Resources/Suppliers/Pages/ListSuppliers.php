<?php

namespace App\Filament\Resources\Suppliers\Pages;

use App\Filament\Actions\ImporSpreadsheet;
use App\Filament\Imports\SupplierImporter;
use App\Filament\Resources\Suppliers\SupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Supplier Obat';

    protected function getHeaderActions(): array
    {
        return [
            ImporSpreadsheet::aksi(SupplierImporter::class, 'Supplier', 'template-supplier'),
            CreateAction::make()
                ->label('Tambah Supplier Obat'),
        ];
    }
}
