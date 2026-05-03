<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'completed';
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Gagal membuat purchase order')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
