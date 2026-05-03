<?php

namespace App\Filament\Resources\MedicineStocks\Pages;

use App\Filament\Resources\MedicineStocks\MedicineStockResource;
use Filament\Resources\Pages\ListRecords;

class ListMedicineStocks extends ListRecords
{
    protected static string $resource = MedicineStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Read-only
        ];
    }
}
