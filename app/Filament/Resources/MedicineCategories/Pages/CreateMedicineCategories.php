<?php

namespace App\Filament\Resources\MedicineCategories\Pages;

use App\Filament\Resources\MedicineCategories\MedicineCategoriesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicineCategories extends CreateRecord
{
    protected static string $resource = MedicineCategoriesResource::class;

    protected static ?string $title = "Tambah Kategori Obat";

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }
}
