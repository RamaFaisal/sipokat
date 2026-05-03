<?php

namespace App\Filament\Resources\ReceiveOrders\Pages;

use App\Filament\Resources\ReceiveOrders\ReceiveOrderResource;
use App\Models\MedicineStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateReceiveOrder extends CreateRecord
{
    protected static string $resource = ReceiveOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['received_by'] = auth()->id();
        $data['status'] = 'completed';
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {

                foreach ($this->data['items'] as $item) {
                    $poItem = PurchaseOrderItem::where('purchase_order_id', $data['purchase_order_id'])
                        ->where('medicine_id', $item['medicine_id'])
                        ->firstOrFail();

                    $receivedQty = ReceiveOrderItem::whereHas('receiveOrder', function ($q) use ($data) {
                        $q->where('purchase_order_id', $data['purchase_order_id'])
                            ->whereIn('status', ['pending', 'partial']);
                    })
                        ->where('medicine_id', $item['medicine_id'])
                        ->sum('qty');

                    if (($receivedQty + $item['qty']) > $poItem->qty) {
                        throw new \Exception("Jumlah {$poItem->medicine->name} yang diterima melebihi PO");
                    }
                }

                $receiveOrder = parent::handleRecordCreation($data);

                return $receiveOrder;
            });
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal membuat Receive Order')
                ->body($e->getMessage())
                ->send();

            throw $e;
        }
    }

    public static function updatePurchaseOrderStatus(int $poId, int $roId): void
    {
        $poItems = PurchaseOrderItem::where('purchase_order_id', $poId)->get();

        foreach ($poItems as $poItem) {
            $receivedQty = ReceiveOrderItem::whereHas('receiveOrder', function ($q) use ($poId) {
                $q->where('purchase_order_id', $poId);
            })
                ->where('medicine_id', $poItem->medicine_id)
                ->sum('qty');

            if ($receivedQty < $poItem->qty) {
                PurchaseOrder::where('id', $poId)->update(['status_receive_order' => 'partial']);
                ReceiveOrder::where('id', $roId)->update(['status' => 'completed']);
                return;
            }

            if ($receivedQty > $poItem->qty) {
                throw new \Exception(
                    "Qty receive melebihi PO untuk obat ID {$poItem->medicine_id}"
                );
            }
        }

        PurchaseOrder::where('id', $poId)->update(['status_receive_order' => 'received']);
        ReceiveOrder::where('id', $roId)->update(['status' => 'completed']);
    }

    protected function afterCreate(): void
    {
        try {
            $receiveOrder = $this->record;

            self::updatePurchaseOrderStatus(
                $receiveOrder->purchase_order_id,
                $receiveOrder->id
            );

            foreach ($receiveOrder->items as $item) {
                MedicineStock::create([
                    'medicine_id' => $item->medicine_id,
                    'qty' => $item->qty,
                    'type_account' => 'D',
                    'date' => $receiveOrder->receive_date,
                    'hpp' => $item->price,
                    'receive_order_id' => $receiveOrder->id,
                    'description' => 'Penerimaan dari ' . $receiveOrder->purchaseOrder->po_number,
                    'created_by' => auth()->id(),
                ]);
            }

        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal memperbarui status PO')
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
