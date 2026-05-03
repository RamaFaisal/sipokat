<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use App\Models\MedicineStock;
use App\Models\MedicineStockOpnameItem;
use App\Services\StockCardService;
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

            $medicineIds = [];

            foreach ($this->form->getState()['medicineStockOpnameItems'] as $medicineData) {
                foreach ($medicineData['medicine_items'] as $detailData) {

                    MedicineStockOpnameItem::create([
                        'medicine_stock_opname_id' => $this->record->id,
                        'medicine_id'              => $medicineData['medicine_id'],
                        'qty'                       => $detailData['qty'],
                        'type_account'              => $detailData['type_account'],
                        'hpp'                       => $detailData['hpp'],
                    ]);

                    MedicineStock::create([
                        'medicine_id'                  => $medicineData['medicine_id'],
                        'qty'                           => $detailData['qty'],
                        'type_account'                  => $detailData['type_account'],
                        'date'                          => $this->record->opname_date,
                        'hpp'                           => $detailData['hpp'],
                        'medicine_stock_opname_id'     => $this->record->id,
                        'description'                   => 'opname dari ' . $this->record->opname_number,
                    ]);

                    $medicineIds[] = $medicineData['medicine_id'];
                    // dd($medicineIds);
                }
            }

            $stockService = app(StockCardService::class);

            foreach (array_unique($medicineIds) as $medicineId) {
                $stockService->updateMedicineStockStatus($medicineId);
            }
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
