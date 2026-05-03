<?php

namespace App\Filament\Resources\ReceiveOrders\Pages;

use App\Filament\Resources\ReceiveOrders\ReceiveOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReceiveOrders extends ListRecords
{
    protected static string $resource = ReceiveOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
