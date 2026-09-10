<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Services\StockMovementService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn () => ! auth()->user()->hasRole('super_admin')),
            ForceDeleteAction::make()
                ->hidden(fn () => ! auth()->user()->hasRole('super_admin')),
            RestoreAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data) {
                $movement = app(StockMovementService::class);
                $items = $this->data['items'] ?? [];

                if (empty($items)) {
                    throw new \Exception('Order harus memiliki minimal 1 item.');
                }

                // Entri lama dibalik lebih dulu supaya validasi ketersediaan
                // melihat stok seolah order ini belum pernah ada.
                $affectedMedicineIds = $movement->reverseSale($record);

                $movement->assertAvailable($items);

                $record = parent::handleRecordUpdate($record, $data);

                $affectedMedicineIds = array_merge(
                    $affectedMedicineIds,
                    $movement->recordSale($record),
                );

                $record->_affected_medicine_ids = array_values(array_unique($affectedMedicineIds));

                return $record;
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal memperbarui Order')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    protected function afterSave(): void
    {
        app(StockMovementService::class)->refreshStockStatus(
            $this->record->_affected_medicine_ids ?? []
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
