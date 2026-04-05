<?php

namespace App\Filament\Resources\MedicineCategories\Pages;

use App\Filament\Resources\MedicineCategories\MedicineCategoriesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicineCategories extends ListRecords
{
    protected static string $resource = MedicineCategoriesResource::class;

    protected static ?string $title = "Kategori Obat";

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kategori Obat'),
        ];
    }
}
