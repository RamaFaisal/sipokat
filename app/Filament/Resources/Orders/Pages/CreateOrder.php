<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Services\StockCardService;
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
                $stockService = app(StockCardService::class);
                $items = $this->data['items'] ?? [];

                if (empty($items)) {
                    throw new \Exception('Order harus memiliki minimal 1 item.');
                }

                foreach ($items as $item) {
                    $medicine = Medicine::findOrFail($item['medicine_id']);
                    $available = $stockService->getAvailableStock($medicine->id);

                    if ((float) $item['qty'] > $available) {
                        throw new \Exception(
                            "Stok {$medicine->name} {$medicine->dosage} tidak mencukupi (tersedia: {$available}, diminta: {$item['qty']})"
                        );
                    }
                }

                $order = parent::handleRecordCreation($data);

                foreach ($order->items as $item) {
                    MedicineStock::create([
                        'medicine_id' => $item->medicine_id,
                        'qty' => $item->qty,
                        'type_account' => 'C',
                        'date' => $order->order_date,
                        'hpp' => $item->price,
                        'order_id' => $order->id,
                        'description' => 'Penjualan '.$order->order_code,
                        'created_by' => auth()->id(),
                    ]);
                }

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
        $stockService = app(StockCardService::class);

        foreach ($this->record->items as $item) {
            $stockService->updateMedicineStockStatus($item->medicine_id);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
