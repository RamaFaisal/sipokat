<?php

namespace App\Filament\Resources\Medicines\Pages;

use App\Filament\Resources\Medicines\MedicineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicines extends ListRecords
{
    protected static string $resource = MedicineResource::class;

    protected static ?string $title = "Obat";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Obat'),
        ];
    }
}
