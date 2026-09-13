<?php

use App\Models\MedicineStock;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Services\StockCardService;
use App\Services\StockMovementService;

/**
 * Karakterisasi HPP rata-rata bergerak dan aturan hapus dokumen
 * (rencana-revisi-2026-09 §4.3, B5, R8). Tabel contoh §4.3 direproduksi persis.
 */
beforeEach(function () {
    seedMasterFixtures();
    $this->movement = app(StockMovementService::class);
    $this->stockCard = app(StockCardService::class);
});

function receiveOn(string $date, $medicine, int $qty, int $price, string $batch = 'B', ?string $ed = '2027-06-01'): ReceiveOrder
{
    static $seq = 0;
    $seq++;

    $ro = ReceiveOrder::create([
        'receive_order_number' => sprintf('RO-HPP-%04d', $seq),
        'supplier_id' => test()->supplier->id,
        'invoice_number' => sprintf('INV-HPP-%04d', $seq),
        'receive_date' => $date,
    ]);
    ReceiveOrderItem::create([
        'receive_order_id' => $ro->id,
        'medicine_id' => $medicine->id,
        'medicine_name' => $medicine->name,
        'pack_unit_id' => $medicine->unit_id,
        'pack_size' => 1,
        'pack_qty' => $qty,
        'qty' => $qty,
        'price' => $price,
        'batch_number' => $batch,
        'expired_date' => $ed,
    ]);
    test()->movement->recordReceipt($ro);

    return $ro;
}

function sellOn(string $date, $medicine, int $qty): Order
{
    static $seq = 0;
    $seq++;

    $order = Order::create([
        'order_code' => sprintf('ORD-HPP-%04d', $seq),
        'no_payment' => sprintf('PAY-HPP-%04d', $seq),
        'order_date' => $date,
        'grand_total' => $qty * 20000,
        'status' => 'paid',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'medicine_id' => $medicine->id,
        'medicine_name' => $medicine->name,
        'qty' => $qty,
        'price' => 20000,
        'total' => $qty * 20000,
    ]);
    test()->movement->recordSale($order);

    return $order;
}

function opnameOutOn(string $date, $medicine, int $qty): MedicineStockOpname
{
    static $seq = 0;
    $seq++;

    $opname = MedicineStockOpname::create([
        'opname_number' => sprintf('OPM-HPP-%04d', $seq),
        'opname_date' => $date,
        'status' => 'in_stock',
    ]);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id,
        'medicine_id' => $medicine->id,
        'qty' => $qty,
        'type_account' => 'C',
        'hpp' => 0,
    ]);
    test()->movement->recordOpname($opname);

    return $opname;
}

function ledgerOf($medicine): array
{
    return MedicineStock::where('medicine_id', $medicine->id)
        ->orderBy('date')->orderBy('id')
        ->get()
        ->map(fn ($r) => [$r->type_account, (int) $r->qty, (int) $r->hpp, $r->hpp_avg])
        ->all();
}

it('mereproduksi tabel contoh §4.3 baris per baris', function () {
    $m = makeMedicine();

    receiveOn('2026-09-01', $m, 5, 22000, 'B1');
    receiveOn('2026-09-05', $m, 20, 16000, 'B2');
    sellOn('2026-09-06', $m, 5);
    sellOn('2026-09-08', $m, 10);
    receiveOn('2026-09-09', $m, 10, 20000, 'B3');
    opnameOutOn('2026-09-12', $m, 2);
    sellOn('2026-09-15', $m, 18);
    receiveOn('2026-09-20', $m, 10, 19000, 'B4');

    expect(ledgerOf($m))->toBe([
        ['D', 5, 22000, 22000],
        ['D', 20, 16000, 17200],
        ['C', 5, 17200, 17200],
        ['C', 10, 17200, 17200],
        ['D', 10, 20000, 18600],
        ['C', 2, 18600, 18600],
        ['C', 18, 18600, 18600],
        ['D', 10, 19000, 19000],
    ]);

    expect($this->stockCard->currentHpp($m->id))->toBe(19000)
        ->and($m->currentHpp())->toBe(19000)
        ->and($this->stockCard->physicalStock($m->id))->toBe(10);
});

it('mempertahankan HPP terakhir saat stok 0 (C4 tidak hilang)', function () {
    $m = makeMedicine();
    receiveOn('2026-09-01', $m, 5, 22000);
    sellOn('2026-09-02', $m, 5);

    expect($this->stockCard->physicalStock($m->id))->toBe(0)
        ->and($this->stockCard->currentHpp($m->id))->toBe(22000);
});

it('membulatkan HPP ke atas ke rupiah bulat', function () {
    $m = makeMedicine();
    receiveOn('2026-09-01', $m, 3, 10000);
    receiveOn('2026-09-02', $m, 4, 10001); // (30000 + 40004) / 7 = 10000,57 → 10001

    expect($this->stockCard->currentHpp($m->id))->toBe(10001);
});

it('menghitung ulang HPP ketika penjualan sebelum suatu penerimaan dihapus', function () {
    $m = makeMedicine();
    receiveOn('2026-09-01', $m, 10, 22000);
    $sale = sellOn('2026-09-03', $m, 5);
    receiveOn('2026-09-05', $m, 20, 16000);

    // (5 × 22.000 + 20 × 16.000) / 25 = 17.200
    expect($this->stockCard->currentHpp($m->id))->toBe(17200);

    $sale->delete();

    // Saldo saat batch 2 masuk kini 10: (10 × 22.000 + 20 × 16.000) / 30 = 18.000
    expect($this->stockCard->currentHpp($m->id))->toBe(18000)
        ->and(MedicineStock::where('order_id', $sale->id)->count())->toBe(0)
        ->and($this->stockCard->physicalStock($m->id))->toBe(30);
});

it('menghapus baris kartu stok sungguhan saat penjualan dihapus, order tetap soft-deleted', function () {
    $m = makeMedicine();
    receiveOn('2026-09-01', $m, 10, 5000);
    $sale = sellOn('2026-09-02', $m, 4);

    $sale->delete();

    expect(Order::withTrashed()->find($sale->id)->trashed())->toBeTrue()
        ->and(MedicineStock::where('order_id', $sale->id)->exists())->toBeFalse()
        ->and($m->fresh()->currentStock())->toBe(10);
});

it('menolak penghapusan RO yang batch-nya sudah dikonsumsi (R8)', function () {
    $m = makeMedicine();
    $ro = receiveOn('2026-09-01', $m, 10, 5000);
    $sale = sellOn('2026-09-02', $m, 4);

    // Atribusi lapisan (alokasi FEFO menyusul di E4) — di sini disetel langsung.
    $layer = MedicineStock::where('receive_order_id', $ro->id)->first();
    MedicineStock::where('order_id', $sale->id)->update(['layer_stock_id' => $layer->id]);

    expect(fn () => $ro->delete())->toThrow(RuntimeException::class, 'sudah terjual');
    expect(ReceiveOrder::find($ro->id))->not->toBeNull()
        ->and($layer->fresh()->remaining)->toBe(6);
});

it('mengizinkan penghapusan RO yang belum dikonsumsi dan memulihkan HPP', function () {
    $m = makeMedicine();
    receiveOn('2026-09-01', $m, 10, 22000);
    $ro2 = receiveOn('2026-09-05', $m, 10, 16000);

    expect($this->stockCard->currentHpp($m->id))->toBe(19000);

    $ro2->delete();

    expect($this->stockCard->currentHpp($m->id))->toBe(22000)
        ->and($this->stockCard->physicalStock($m->id))->toBe(10);
});

it('menulis lapisan pada baris D dari RO: batch, ED, dan jejak item', function () {
    $m = makeMedicine();
    $ro = receiveOn('2026-09-01', $m, 10, 5000, 'T10088BC', '2026-10-01');

    $layer = MedicineStock::layers()->where('receive_order_id', $ro->id)->first();

    expect($layer->batch_number)->toBe('T10088BC')
        ->and($layer->expired_date->toDateString())->toBe('2026-10-01')
        ->and($layer->receive_order_item_id)->toBe($ro->items()->first()->id)
        ->and($layer->remaining)->toBe(10);
});
