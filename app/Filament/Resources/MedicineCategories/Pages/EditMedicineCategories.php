<?php

namespace App\Filament\Resources\MedicineCategories\Pages;

use App\Filament\Resources\MedicineCategories\MedicineCategoriesResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicineCategories extends EditRecord
{
    protected static string $resource = MedicineCategoriesResource::class;

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
