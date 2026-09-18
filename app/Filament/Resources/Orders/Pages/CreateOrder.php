<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\StockMovementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['grand_total'] = collect($this->data['items'] ?? [])
            ->sum(fn ($row) => ((int) ($row['qty'] ?? 0)) * (\App\Filament\Forms\PackLine::toNumber($row['price'] ?? null) ?? 0));

        return $data;
    }

    /**
     * Item baru tersimpan setelah handleRecordCreation (saveRelationships), jadi kartu stok
     * ditulis di sini — masih di dalam transaksi Filament: gagal alokasi = seluruhnya batal.
     */
    protected function afterCreate(): void
    {
        /** @var Order $order */
        $order = $this->record;

        try {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->recordSale($order));
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Penjualan dibatalkan')->body($e->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
