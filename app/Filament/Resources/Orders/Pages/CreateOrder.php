<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
