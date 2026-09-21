<?php

namespace Database\Seeders;

use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\StockCardService;
use Illuminate\Database\Seeder;

/**
 * Beberapa PO contoh (P1) supaya menu Pemesanan tidak kosong pada deploy baru — dipilihkan
 * obat dengan rasio stok (C1) terendah, seolah dibuat dari hasil ranking SAW. Hanya jalan
 * bila tabel masih kosong (idempoten: tidak menambah PO setiap kali seeder diulang).
 */
class PurchaseOrderSeeder extends Seeder
{
    public function run(StockCardService $stockCard): void
    {
        if (PurchaseOrder::query()->exists()) {
            $this->command?->info('PurchaseOrderSeeder: sudah ada PO, dilewati.');

            return;
        }

        $suppliers = Supplier::where('status', 'active')->orderBy('id')->limit(2)->get();
        if ($suppliers->isEmpty()) {
            $this->command?->warn('PurchaseOrderSeeder: belum ada PBF, dilewati.');

            return;
        }

        $candidates = Medicine::where('status', 'active')->get()
            ->map(fn (Medicine $m) => [
                'medicine' => $m,
                'ratio' => $stockCard->availableStock($m->id) / max(1, $m->min_stock),
            ])
            ->sortBy('ratio')
            ->take(10)
            ->values();

        if ($candidates->isEmpty()) {
            $this->command?->warn('PurchaseOrderSeeder: belum ada obat, dilewati.');

            return;
        }

        $chunks = $candidates->chunk((int) ceil($candidates->count() / $suppliers->count()));

        foreach ($suppliers->values() as $i => $supplier) {
            $rows = $chunks->get($i);
            if (! $rows || $rows->isEmpty()) {
                continue;
            }

            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrderForm::generatePONumber(),
                'supplier_id' => $supplier->id,
                'po_date' => now(),
                'status_receive_order' => PurchaseOrder::STATUS_PENDING,
            ]);

            foreach ($rows as $row) {
                /** @var Medicine $medicine */
                $medicine = $row['medicine'];
                $packSize = max(1, (int) $medicine->pack_size);
                $packQty = max(1, (int) ceil($medicine->min_stock / $packSize));
                $price = $medicine->latestPurchasePrice() ?? (float) ($medicine->currentHpp() ?? 0);

                $po->items()->create([
                    'medicine_id' => $medicine->id,
                    'pack_unit_id' => $medicine->pack_unit_id,
                    'pack_size' => $packSize,
                    'pack_qty' => $packQty,
                    'qty' => $packQty * $packSize,
                    'price' => $price,
                ]);
            }

            $this->command?->info("PurchaseOrderSeeder: {$po->po_number} ({$supplier->name}, {$rows->count()} obat).");
        }
    }
}
