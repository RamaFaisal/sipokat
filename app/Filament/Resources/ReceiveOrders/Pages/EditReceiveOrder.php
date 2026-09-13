<?php

namespace App\Filament\Resources\ReceiveOrders\Pages;

use App\Filament\Resources\ReceiveOrders\ReceiveOrderResource;
use App\Models\ReceiveOrder;
use App\Services\StockMovementService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

/**
 * Edit RO = faktur revisi (R10). Batch/ED/harga selalu boleh diubah; jumlah dan penghapusan
 * baris dibatasi sisa lapisan (R8) — dilakukan di afterSave dalam transaksi Filament,
 * jadi pelanggaran membatalkan seluruh perubahan.
 */
class EditReceiveOrder extends EditRecord
{
    protected static string $resource = ReceiveOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (ReceiveOrder $record) {
                    try {
                        return $record->delete();
                    } catch (\RuntimeException $e) {
                        Notification::make()->danger()->title('Tidak dapat dihapus')->body($e->getMessage())->persistent()->send();

                        return false;
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        /** @var ReceiveOrder $receiveOrder */
        $receiveOrder = $this->record->fresh();

        try {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->syncReceipt($receiveOrder));
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Perubahan dibatalkan')->body($e->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        $receiveOrder->purchaseOrder?->refreshReceiveStatus();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
