<?php

namespace App\Filament\Resources\MedicineRacks\Pages;

use App\Filament\Resources\MedicineRacks\MedicineRackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicineRacks extends ListRecords
{
    protected static string $resource = MedicineRackResource::class;

    protected static ?string $title = "Rak Obat";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Rak Obat'),
        ];
    }
}
