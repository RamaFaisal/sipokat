<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use App\Models\MedicineStockOpnameItem;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicineStockOpname extends EditRecord
{
    protected static string $resource = MedicineStockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;
        
        if ($record) {
            $groupedItems = $record->medicineStockOpnameItems
                ->groupBy('medicine_id')
                ->map(function ($items, $medicineId) {
                    return [
                        'medicine_id' => $medicineId,
                        'medicine_items' => $items->map(function ($item) {
                            return [
                                'qty' => $item->qty,
                                'type_account' => $item->type_account,
                                'hpp' => $item->hpp,
                            ];
                        })->toArray(),
                    ];
                })
                ->values()
                ->toArray();
            
            $data['medicineStockOpnameItems'] = $groupedItems;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function afterSave(): void
    {
        // Delete all existing items
        $this->record->medicineStockOpnameItems()->delete();
        
        // Recreate all items from form data
        $items = $this->form->getState()['medicineStockOpnameItems'] ?? [];
        
        foreach ($items as $medicineData) {
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
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
