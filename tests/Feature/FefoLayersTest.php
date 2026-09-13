<?php

use App\Models\MedicineStock;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Services\StockCardService;
use App\Services\StockMovementService;

/**
 * Karakterisasi stok per lapisan & FEFO (rencana-revisi-2026-09 Bagian 5, B4, F0–F6).
 */
beforeEach(function () {
    seedMasterFixtures();
    $this->movement = app(StockMovementService::class);
    $this->stockCard = app(StockCardService::class);
});

function layersOf($medicine): array
{
    return app(StockCardService::class)->layers($medicine->id)
        ->map(fn ($l) => [$l->batch_number, $l->remaining])
        ->all();
}

it('memecah penjualan 7 menjadi 5 dari batch terdekat dan 2 dari batch berikutnya', function () {
    $m = makeMedicine();
    receiveInto($m, 5, '2027-05-01', 'B1');
    receiveInto($m, 20, '2027-12-01', 'B2');

    $order = sellFrom($m, 7);

    $rows = MedicineStock::where('order_id', $order->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and((int) $rows[0]->qty)->toBe(5)->and($rows[0]->layer->batch_number)->toBe('B1')
        ->and((int) $rows[1]->qty)->toBe(2)->and($rows[1]->layer->batch_number)->toBe('B2')
        ->and(layersOf($m))->toBe([['B1', 0], ['B2', 18]])
        ->and($m->currentStock())->toBe(18);
});

it('melewati batch kedaluwarsa saat alokasi dan menolak bila stok layak tidak cukup (F1)', function () {
    $m = makeMedicine();
    receiveInto($m, 10, now()->subDays(5)->toDateString(), 'EXP');
    receiveInto($m, 4, '2027-12-01', 'OK');

    expect($this->stockCard->availableStock($m->id))->toBe(4)
        ->and($this->stockCard->physicalStock($m->id))->toBe(14);

    expect(fn () => sellFrom($m, 5))->toThrow(RuntimeException::class, 'tersedia: 4');

    $order = sellFrom($m, 4);
    expect(MedicineStock::where('order_id', $order->id)->first()->layer->batch_number)->toBe('OK');
});

it('menganggap batch ber-ED tepat hari ini sudah kedaluwarsa (B1)', function () {
    $m = makeMedicine();
    receiveInto($m, 10, today()->toDateString(), 'TODAY');

    expect($this->stockCard->availableStock($m->id))->toBe(0)
        ->and($this->stockCard->physicalStock($m->id))->toBe(10);
});

it('menghapus penjualan memulihkan sisa lapisan yang dikurangi', function () {
    $m = makeMedicine();
    receiveInto($m, 5, '2027-05-01', 'B1');
    receiveInto($m, 20, '2027-12-01', 'B2');
    $order = sellFrom($m, 7);

    $order->delete();

    expect(layersOf($m))->toBe([['B1', 5], ['B2', 20]]);
});

it('menetapkan status stok dari stok tersedia, bukan stok fisik (B4)', function () {
    $m = makeMedicine(['min_stock' => 20]);
    receiveInto($m, 30, now()->subDays(1)->toDateString(), 'EXP'); // fisik 30, tersedia 0

    $this->stockCard->updateMedicineStockStatus($m->id);

    expect($m->fresh()->stock_status)->toBe('empty');
});

it('opname per lapisan: fisik lebih kecil → baris C pada batch itu, termasuk batch kedaluwarsa', function () {
    $m = makeMedicine();
    receiveInto($m, 10, now()->subDays(5)->toDateString(), 'EXP');
    receiveInto($m, 20, '2027-12-01', 'OK');
    $exp = MedicineStock::layers()->where('batch_number', 'EXP')->first();

    $opname = MedicineStockOpname::create(['opname_number' => 'OPM-T1', 'opname_date' => today(), 'status' => 'in_stock']);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id, 'medicine_id' => $m->id,
        'layer_stock_id' => $exp->id, 'qty' => 10, 'type_account' => 'C', 'hpp' => 0,
    ]);
    $this->movement->recordOpname($opname);

    expect(layersOf($m))->toBe([['EXP', 0], ['OK', 20]])
        ->and($this->stockCard->physicalStock($m->id))->toBe(20);
});

it('opname per lapisan: batch baru menjadi lapisan dengan batch & ED-nya sendiri', function () {
    $m = makeMedicine();
    receiveInto($m, 10, '2027-05-01', 'B1');

    $opname = MedicineStockOpname::create(['opname_number' => 'OPM-T2', 'opname_date' => today(), 'status' => 'in_stock']);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id, 'medicine_id' => $m->id,
        'batch_number' => 'BARU', 'expired_date' => '2028-01-01', 'qty' => 3, 'type_account' => 'D', 'hpp' => 0,
    ]);
    $this->movement->recordOpname($opname);

    $new = MedicineStock::layers()->where('batch_number', 'BARU')->first();
    expect($new)->not->toBeNull()
        ->and($new->expired_date->toDateString())->toBe('2028-01-01')
        ->and((int) $new->hpp)->toBe(FIXTURE_PURCHASE_PRICE) // HPP saat itu, bukan harga baru (S4)
        ->and($this->stockCard->availableStock($m->id))->toBe(13);
});

it('opname penambahan ditolak untuk obat tanpa riwayat harga (§7.3)', function () {
    $m = makeMedicine();

    $opname = MedicineStockOpname::create(['opname_number' => 'OPM-T3', 'opname_date' => today(), 'status' => 'in_stock']);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id, 'medicine_id' => $m->id,
        'batch_number' => 'X', 'expired_date' => '2028-01-01', 'qty' => 3, 'type_account' => 'D', 'hpp' => 0,
    ]);

    expect(fn () => $this->movement->recordOpname($opname))->toThrow(RuntimeException::class, 'Penerimaan');
});

it('backfill F6: baris C lama diatribusikan FEFO dan dipecah bila melintasi lapisan', function () {
    $m = makeMedicine();
    receiveInto($m, 5, '2027-05-01', 'B1');
    receiveInto($m, 20, '2027-12-01', 'B2');

    // Baris C "warisan" tanpa lapisan, seperti data sebelum revisi.
    $legacy = MedicineStock::create([
        'medicine_id' => $m->id, 'qty' => 7, 'type_account' => 'C', 'date' => today()->toDateString(), 'hpp' => 0,
    ]);

    $unattributed = $this->movement->backfillLayers($m->id);

    $rows = MedicineStock::where('medicine_id', $m->id)->where('type_account', 'C')->orderBy('id')->get();
    expect($unattributed)->toBe(0)
        ->and($rows)->toHaveCount(2)
        ->and((int) $rows[0]->qty)->toBe(5)->and($rows[0]->layer->batch_number)->toBe('B1')
        ->and((int) $rows[1]->qty)->toBe(2)->and($rows[1]->layer->batch_number)->toBe('B2')
        ->and(layersOf($m))->toBe([['B1', 0], ['B2', 18]]);
});

it('backfill F6 mencatat sisa yang tidak bisa diatribusikan tanpa mengubah stok fisik', function () {
    $m = makeMedicine();
    receiveInto($m, 5, '2027-05-01', 'B1');
    MedicineStock::create([
        'medicine_id' => $m->id, 'qty' => 8, 'type_account' => 'C', 'date' => today()->toDateString(), 'hpp' => 0,
    ]);

    $unattributed = $this->movement->backfillLayers($m->id);

    expect($unattributed)->toBe(3)
        ->and($this->stockCard->physicalStock($m->id))->toBe(-3)
        ->and($this->stockCard->availableStock($m->id))->toBe(0);
});
