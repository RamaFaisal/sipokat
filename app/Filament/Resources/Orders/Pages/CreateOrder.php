<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\StockMovementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'paid';

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {
                $movement = app(StockMovementService::class);
                $items = $this->data['items'] ?? [];

                if (empty($items)) {
                    throw new \Exception('Order harus memiliki minimal 1 item.');
                }

                $movement->assertAvailable($items);

                $order = parent::handleRecordCreation($data);

                $movement->recordSale($order);

                return $order;
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal membuat Order')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        app(StockMovementService::class)->refreshStockStatus(
            $this->record->items()->pluck('medicine_id')->all()
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
