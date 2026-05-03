<?php

namespace App\Filament\Resources\ReceiveOrders\Pages;

use App\Filament\Resources\ReceiveOrders\ReceiveOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditReceiveOrder extends EditRecord
{
    protected static string $resource = ReceiveOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
