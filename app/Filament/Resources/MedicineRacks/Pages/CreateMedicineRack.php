<?php

namespace App\Filament\Resources\MedicineRacks\Pages;

use App\Filament\Resources\MedicineRacks\MedicineRackResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicineRack extends CreateRecord
{
    protected static string $resource = MedicineRackResource::class;

    protected static ?string $title = "Tambah Rak Obat";

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    public function canCreateAnother(): bool
    {
        return false;
    }
}
