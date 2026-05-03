<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicineStockOpnames extends ListRecords
{
    protected static string $resource = MedicineStockOpnameResource::class;

    protected static ?string $title = "Stok Opname";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
