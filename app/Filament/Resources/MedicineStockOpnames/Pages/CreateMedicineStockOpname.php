<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use App\Models\MedicineStockOpnameItem;
use App\Services\StockMovementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMedicineStockOpname extends CreateRecord
{
    protected static string $resource = MedicineStockOpnameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            foreach ($this->form->getState()['medicineStockOpnameItems'] as $medicineData) {
                foreach ($medicineData['medicine_items'] as $detailData) {
                    MedicineStockOpnameItem::create([
                        'medicine_stock_opname_id' => $this->record->id,
                        'medicine_id' => $medicineData['medicine_id'],
                        'qty' => $detailData['qty'],
                        'type_account' => $detailData['type_account'],
                        'hpp' => $detailData['hpp'],
                    ]);
                }
            }

            // Kartu stok ditulis lewat service: HPP baris mengikuti rata-rata bergerak (S4),
            // bukan angka dari form.
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->recordOpname($this->record));
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal menyimpan stok opname')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            throw $e;
        }
    }
}
