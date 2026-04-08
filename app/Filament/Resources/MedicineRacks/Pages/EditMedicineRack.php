<?php

namespace App\Filament\Resources\MedicineRacks\Pages;

use App\Filament\Resources\MedicineRacks\MedicineRackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicineRack extends EditRecord
{
    protected static string $resource = MedicineRackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
