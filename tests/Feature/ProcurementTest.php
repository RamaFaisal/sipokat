<?php

use App\Filament\Forms\PackLine;
use App\Filament\Resources\ReceiveOrders\Schemas\ReceiveOrderForm;
use App\Models\MedicineStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Services\StockMovementService;

/**
 * Karakterisasi pengadaan (rencana-revisi-2026-09 Bagian 2 & 3): konversi kemasan,
 * status PO turunan dari sisa, satu PO → banyak RO, dan aturan edit RO (R8).
 */
beforeEach(function () {
    seedMasterFixtures();
    $this->movement = app(StockMovementService::class);
});

function poWith(array $lines): PurchaseOrder
{
    $po = makePurchaseOrder();
    foreach ($lines as [$medicine, $qty, $price]) {
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'medicine_id' => $medicine->id,
            'pack_unit_id' => $medicine->pack_unit_id,
            'pack_size' => $medicine->pack_size,
            'pack_qty' => (int) ceil($qty / max(1, $medicine->pack_size)),
            'qty' => $qty,
            'price' => $price,
        ]);
    }

    return $po;
}

function roFor(PurchaseOrder $po, array $lines): ReceiveOrder
{
    static $seq = 0;
    $seq++;

    $ro = ReceiveOrder::create([
        'receive_order_number' => ReceiveOrder::nextNumber(),
        'invoice_number' => sprintf('F-%04d', $seq),
        'purchase_order_id' => $po->id,
        'supplier_id' => $po->supplier_id,
        'receive_date' => now()->toDateString(),
    ]);
    foreach ($lines as [$medicine, $qty, $price]) {
        ReceiveOrderItem::create([
            'receive_order_id' => $ro->id,
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->name,
            'pack_unit_id' => $medicine->unit_id,
            'pack_size' => 1,
            'pack_qty' => $qty,
            'qty' => $qty,
            'price' => $price,
            'batch_number' => 'B-'.$seq,
            'expired_date' => now()->addYear()->startOfMonth()->toDateString(),
        ]);
    }
    test()->movement->recordReceipt($ro);
    $po->refreshReceiveStatus();

    return $ro;
}

it('mengonversi baris kemasan ke satuan jual: 5 Box isi 10 @ 41.000 → 50 @ 4.100', function () {
    $data = PackLine::dehydrate(['pack_unit_id' => 9, 'pack_size' => 10, 'pack_qty' => 5, 'pack_price' => 41000]);

    expect($data['qty'])->toBe(50)
        ->and($data['price'])->toBe(4100.0)
        ->and($data)->not->toHaveKey('pack_price');

    // Saat form diisi ulang, harga per kemasan kembali seperti di faktur.
    expect(PackLine::hydrate(['pack_size' => 10, 'price' => 4100])['pack_price'])->toBe(41000.0);
});

it('mengonversi eceran (satuan jual, isi 1) apa adanya', function () {
    $data = PackLine::dehydrate(['pack_unit_id' => 1, 'pack_size' => 1, 'pack_qty' => 5, 'pack_price' => 4100]);

    expect($data['qty'])->toBe(5)->and($data['price'])->toBe(4100.0);
});

it('menyimpan ED bulan-tahun sebagai tanggal 1 bulan itu', function () {
    $data = ReceiveOrderForm::dehydrateItem([
        'medicine_id' => makeMedicine()->id,
        'pack_size' => 1, 'pack_qty' => 1, 'pack_price' => 100,
        'expired_month' => '10-2026',
    ]);

    expect($data['expired_date'])->toBe('2026-10-01')
        ->and(ReceiveOrderForm::hydrateItem(['pack_size' => 1, 'price' => 100, 'expired_date' => '2026-10-01'])['expired_month'])->toBe('10-2026');
});

it('menurunkan status PO dari sisa: belum → sebagian → lengkap, lewat beberapa RO', function () {
    $a = makeMedicine();
    $b = makeMedicine();
    $po = poWith([[$a, 100, 4100], [$b, 20, 7000]]);

    expect($po->status_receive_order)->toBe(PurchaseOrder::STATUS_PENDING);

    roFor($po, [[$a, 50, 4100]]);
    expect($po->fresh()->status_receive_order)->toBe(PurchaseOrder::STATUS_PARTIAL)
        ->and($po->remainingByMedicine())->toBe([$a->id => 50, $b->id => 20]);

    roFor($po, [[$a, 30, 4100], [$a, 20, 4100]]); // dua batch untuk obat yang sama dalam satu faktur
    roFor($po, [[$b, 20, 7000]]);

    expect($po->fresh()->status_receive_order)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and(array_sum($po->remainingByMedicine()))->toBe(0);
});

it('mengembalikan status PO saat sebuah RO dihapus', function () {
    $a = makeMedicine();
    $po = poWith([[$a, 10, 1000]]);
    $ro = roFor($po, [[$a, 10, 1000]]);

    expect($po->fresh()->status_receive_order)->toBe(PurchaseOrder::STATUS_RECEIVED);

    $ro->delete();

    expect($po->fresh()->status_receive_order)->toBe(PurchaseOrder::STATUS_PENDING);
});

it('membiarkan PO yang sudah ditutup meski ada penerimaan menyusul', function () {
    $a = makeMedicine();
    $po = poWith([[$a, 10, 1000]]);
    $po->forceFill(['status_receive_order' => PurchaseOrder::STATUS_CLOSED])->save();

    roFor($po, [[$a, 10, 1000]]);

    expect($po->fresh()->status_receive_order)->toBe(PurchaseOrder::STATUS_CLOSED);
});

it('menawarkan hanya item PO yang masih bersisa untuk dicentang', function () {
    $a = makeMedicine();
    $b = makeMedicine();
    $po = poWith([[$a, 10, 1000], [$b, 5, 2000]]);
    roFor($po, [[$a, 10, 1000]]);

    $options = ReceiveOrderForm::poRemainingOptions($po->id);

    expect($options)->toHaveKey($b->id)
        ->and($options)->not->toHaveKey($a->id)
        ->and($options[$b->id])->toContain('sisa 5');
});

it('menomori RO per hari: RO{YYYYMMDD}-0001, -0002', function () {
    $a = makeMedicine();
    $po = poWith([[$a, 10, 1000]]);
    $first = roFor($po, [[$a, 1, 1000]]);
    $second = roFor($po, [[$a, 1, 1000]]);

    $prefix = 'RO'.now()->format('Ymd').'-';
    expect($first->receive_order_number)->toBe($prefix.'0001')
        ->and($second->receive_order_number)->toBe($prefix.'0002');
});

it('edit RO: batch, ED, dan harga boleh berubah walau batch sudah terjual', function () {
    $a = makeMedicine();
    $po = poWith([[$a, 10, 1000]]);
    $ro = roFor($po, [[$a, 10, 1000]]);
    $layer = MedicineStock::layers()->where('receive_order_id', $ro->id)->first();
    MedicineStock::create([
        'medicine_id' => $a->id, 'qty' => 4, 'type_account' => 'C', 'date' => now()->toDateString(),
        'hpp' => 1000, 'layer_stock_id' => $layer->id,
    ]);

    $ro->items()->first()->update(['batch_number' => 'REVISI', 'expired_date' => '2028-01-01', 'price' => 1200]);
    $this->movement->syncReceipt($ro->fresh());

    $layer->refresh();
    expect($layer->batch_number)->toBe('REVISI')
        ->and($layer->expired_date->toDateString())->toBe('2028-01-01')
        ->and((int) $layer->hpp)->toBe(1200)
        ->and($layer->hpp_avg)->toBe(1200);
});

it('edit RO: jumlah tidak boleh di bawah yang sudah terjual (R8)', function () {
    $a = makeMedicine();
    $po = poWith([[$a, 10, 1000]]);
    $ro = roFor($po, [[$a, 10, 1000]]);
    $layer = MedicineStock::layers()->where('receive_order_id', $ro->id)->first();
    MedicineStock::create([
        'medicine_id' => $a->id, 'qty' => 6, 'type_account' => 'C', 'date' => now()->toDateString(),
        'hpp' => 1000, 'layer_stock_id' => $layer->id,
    ]);

    $ro->items()->first()->update(['qty' => 5, 'pack_qty' => 5]);

    expect(fn () => $this->movement->syncReceipt($ro->fresh()))
        ->toThrow(RuntimeException::class, 'sudah terjual');
});

it('edit RO: item baru menjadi lapisan baru, item yang dihapus (belum terjual) hilang lapisannya', function () {
    $a = makeMedicine();
    $b = makeMedicine();
    $po = poWith([[$a, 10, 1000], [$b, 5, 500]]);
    $ro = roFor($po, [[$a, 10, 1000]]);

    $ro->items()->first()->delete();
    ReceiveOrderItem::create([
        'receive_order_id' => $ro->id, 'medicine_id' => $b->id, 'medicine_name' => $b->name,
        'pack_unit_id' => $b->unit_id, 'pack_size' => 1, 'pack_qty' => 5, 'qty' => 5, 'price' => 500,
        'batch_number' => 'NEW', 'expired_date' => '2027-01-01',
    ]);

    $this->movement->syncReceipt($ro->fresh());

    expect(MedicineStock::layers()->where('receive_order_id', $ro->id)->count())->toBe(1)
        ->and($a->fresh()->currentStock())->toBe(0)
        ->and($b->fresh()->currentStock())->toBe(5);
});
