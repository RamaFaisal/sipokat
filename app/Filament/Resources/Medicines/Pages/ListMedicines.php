<?php

namespace App\Filament\Resources\Medicines\Pages;

use App\Filament\Actions\ImporSpreadsheet;
use App\Filament\Imports\MedicineImporter;
use App\Filament\Resources\Medicines\MedicineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicines extends ListRecords
{
    protected static string $resource = MedicineResource::class;

    protected static ?string $title = 'Obat';

    protected function getHeaderActions(): array
    {
        return [
            ImporSpreadsheet::aksi(MedicineImporter::class, 'Obat', 'template-obat'),
            CreateAction::make()
                ->label('Tambah Obat'),
        ];
    }
}
