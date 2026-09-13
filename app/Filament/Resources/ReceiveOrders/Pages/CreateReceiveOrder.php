<?php

namespace App\Filament\Resources\ReceiveOrders\Pages;

use App\Filament\Resources\ReceiveOrders\ReceiveOrderResource;
use App\Models\ReceiveOrder;
use App\Services\StockMovementService;
use Filament\Resources\Pages\CreateRecord;

class CreateReceiveOrder extends CreateRecord
{
    protected static string $resource = ReceiveOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['received_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var ReceiveOrder $receiveOrder */
        $receiveOrder = $this->record;

        $movement = app(StockMovementService::class);
        $movement->refreshStockStatus($movement->recordReceipt($receiveOrder));

        $receiveOrder->purchaseOrder?->refreshReceiveStatus();
    }

    /**
     * "Simpan & buat lagi" (R12): delapan faktur sehari dari PO dan PBF yang sama —
     * header berikutnya tinggal nomor faktur.
     */
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return [
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'receive_date' => $data['receive_date'] ?? null,
            'receive_order_number' => ReceiveOrder::nextNumber(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
