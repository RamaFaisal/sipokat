<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Services\StockCardService;
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
                $stockService = app(StockCardService::class);
                $items = $this->data['items'] ?? [];

                if (empty($items)) {
                    throw new \Exception('Order harus memiliki minimal 1 item.');
                }

                $affectedMedicineIds = MedicineStock::where('order_id', $record->id)
                    ->pluck('medicine_id')
                    ->all();

                MedicineStock::where('order_id', $record->id)->delete();

                foreach ($items as $item) {
                    $medicine = Medicine::findOrFail($item['medicine_id']);
                    $available = $stockService->getAvailableStock($medicine->id);

                    if ((float) $item['qty'] > $available) {
                        throw new \Exception(
                            "Stok {$medicine->name} {$medicine->dosage} tidak mencukupi (tersedia: {$available}, diminta: {$item['qty']})"
                        );
                    }
                }

                $record = parent::handleRecordUpdate($record, $data);

                foreach ($record->fresh()->items as $item) {
                    MedicineStock::create([
                        'medicine_id' => $item->medicine_id,
                        'qty' => $item->qty,
                        'type_account' => 'C',
                        'date' => $record->order_date,
                        'hpp' => $item->price,
                        'order_id' => $record->id,
                        'description' => 'Penjualan '.$record->order_code,
                        'created_by' => auth()->id(),
                    ]);

                    $affectedMedicineIds[] = $item->medicine_id;
                }

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
        $stockService = app(StockCardService::class);

        $medicineIds = $this->record->_affected_medicine_ids ?? [];

        foreach ($medicineIds as $medicineId) {
            $stockService->updateMedicineStockStatus($medicineId);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
