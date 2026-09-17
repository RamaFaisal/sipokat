<?php

namespace App\Filament\Resources\MedicineStockOpnames\Pages;

use App\Filament\Resources\MedicineStockOpnames\MedicineStockOpnameResource;
use App\Filament\Resources\MedicineStockOpnames\Schemas\MedicineStockOpnameForm;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Services\StockCardService;
use App\Services\StockMovementService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateMedicineStockOpname extends CreateRecord
{
    protected static string $resource = MedicineStockOpnameResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        unset($data['lines']);

        return $data;
    }

    /**
     * Selisih fisik vs sistem per lapisan → item opname (C/D pada lapisan itu); batch baru → item D
     * dengan batch/ED. Kartu stok ditulis service dalam transaksi Filament — gagal = batal semua.
     */
    protected function afterCreate(): void
    {
        /** @var MedicineStockOpname $opname */
        $opname = $this->record;
        $stockCard = app(StockCardService::class);
        $created = 0;

        foreach ($this->data['lines'] ?? [] as $line) {
            $medicineId = (int) ($line['medicine_id'] ?? 0);
            if (! $medicineId) {
                continue;
            }
            $hpp = $stockCard->currentHpp($medicineId) ?? 0;

            foreach ($line['layers'] ?? [] as $row) {
                $diff = (int) ($row['physical_qty'] ?? 0) - (int) ($row['system_qty'] ?? 0);
                if ($diff === 0) {
                    continue;
                }
                MedicineStockOpnameItem::create([
                    'medicine_stock_opname_id' => $opname->id,
                    'medicine_id' => $medicineId,
                    'layer_stock_id' => $row['layer_stock_id'] ?? null,
                    'qty' => abs($diff),
                    'type_account' => $diff < 0 ? 'C' : 'D',
                    'hpp' => $hpp,
                    'note' => $row['note'] ?? null,
                ]);
                $created++;
            }

            foreach ($line['new_batches'] ?? [] as $row) {
                $qty = (int) ($row['qty'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                MedicineStockOpnameItem::create([
                    'medicine_stock_opname_id' => $opname->id,
                    'medicine_id' => $medicineId,
                    'batch_number' => $row['batch_number'] ?? null,
                    'expired_date' => MedicineStockOpnameForm::parseExpiredMonth($row['expired_month'] ?? null),
                    'qty' => $qty,
                    'type_account' => 'D',
                    'hpp' => $hpp,
                    'note' => $row['note'] ?? null,
                ]);
                $created++;
            }
        }

        if ($created === 0) {
            Notification::make()->warning()->title('Tidak ada selisih')->body('Semua jumlah fisik sama dengan sistem — opname tidak disimpan.')->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }

        try {
            $movement = app(StockMovementService::class);
            $movement->refreshStockStatus($movement->recordOpname($opname));
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title('Opname dibatalkan')->body($e->getMessage())->persistent()->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
