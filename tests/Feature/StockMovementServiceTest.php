<?php

/*
|--------------------------------------------------------------------------
| Tes StockMovementService
|--------------------------------------------------------------------------
|
| Service ini memindahkan penulisan kartu stok keluar dari kelas halaman
| Filament, supaya alokasi FEFO nanti hanya perlu ditulis sekali (CLAUDE.md
| Section 13, Tahap 3) dan sisi tulis bisa diuji tanpa Livewire.
|
*/

use App\Models\MedicineStock;
use App\Services\StockMovementService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-10 08:00:00'));

    seedMasterFixtures();

    $this->service = app(StockMovementService::class);
});

// ---------------------------------------------------------------------------
// Penerimaan
// ---------------------------------------------------------------------------

it('menulis entri debit untuk penerimaan', function () {
    $medicine = makeMedicine();
    $receiveOrder = makeReceiveOrder($medicine, 120);

    $this->service->recordReceipt($receiveOrder);

    expect($medicine->currentStock())->toBe(120);

    $entry = MedicineStock::where('receive_order_id', $receiveOrder->id)->sole();

    expect($entry->type_account)->toBe('D')
        ->and($entry->qty)->toBe(120)
        ->and((float) $entry->hpp)->toBe(5000.0);
});

it('mencantumkan nomor PO pada keterangan penerimaan', function () {
    $medicine = makeMedicine();
    $purchaseOrder = makePurchaseOrder();
    $receiveOrder = makeReceiveOrder($medicine, 50, null, 'B-001', $purchaseOrder);

    $this->service->recordReceipt($receiveOrder);

    $entry = MedicineStock::where('receive_order_id', $receiveOrder->id)->sole();

    expect($entry->description)->toBe('Penerimaan dari ' . $purchaseOrder->po_number);
});

it('tetap menulis stok untuk penerimaan tanpa PO', function () {
    // Perilaku lama memanggil $receiveOrder->purchaseOrder->po_number tanpa
    // memeriksa null, padahal purchase_order_id boleh kosong dan form RO
    // tidak mewajibkannya. Lihat CLAUDE.md Section 13.6.
    $medicine = makeMedicine();
    $receiveOrder = makeReceiveOrder($medicine, 75);

    expect($receiveOrder->purchase_order_id)->toBeNull();

    $this->service->recordReceipt($receiveOrder);

    $entry = MedicineStock::where('receive_order_id', $receiveOrder->id)->sole();

    expect($medicine->currentStock())->toBe(75)
        ->and($entry->description)->toContain($receiveOrder->receive_order_number);
});

it('melaporkan obat yang terdampak penerimaan', function () {
    $medicine = makeMedicine();
    $receiveOrder = makeReceiveOrder($medicine, 10);

    $affected = $this->service->recordReceipt($receiveOrder);

    expect($affected)->toBe([$medicine->id]);
});

// ---------------------------------------------------------------------------
// Penjualan
// ---------------------------------------------------------------------------

it('menulis entri kredit untuk penjualan', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 100);
    $order = makeOrder($medicine, 30);

    $this->service->recordSale($order);

    expect($medicine->currentStock())->toBe(70);

    $entry = MedicineStock::where('order_id', $order->id)->sole();

    expect($entry->type_account)->toBe('C')
        ->and($entry->qty)->toBe(30)
        ->and($entry->description)->toBe('Penjualan ' . $order->order_code);
});

it('mengembalikan stok saat penjualan dibatalkan', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 100);
    $order = makeOrder($medicine, 30);
    $this->service->recordSale($order);

    expect($medicine->currentStock())->toBe(70);

    $this->service->reverseSale($order);

    expect($medicine->currentStock())->toBe(100)
        ->and(MedicineStock::where('order_id', $order->id)->count())->toBe(0);
});

it('melaporkan obat yang terdampak pembatalan penjualan', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 100);
    $order = makeOrder($medicine, 30);
    $this->service->recordSale($order);

    $affected = $this->service->reverseSale($order);

    expect($affected)->toBe([$medicine->id]);
});

// ---------------------------------------------------------------------------
// Validasi ketersediaan
// ---------------------------------------------------------------------------

it('menolak penjualan yang melebihi stok tersedia', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 10);

    $this->service->assertAvailable([
        ['medicine_id' => $medicine->id, 'qty' => 11],
    ]);
})->throws(Exception::class, 'tidak mencukupi');

it('meloloskan penjualan tepat sebesar stok tersedia', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 10);

    $this->service->assertAvailable([
        ['medicine_id' => $medicine->id, 'qty' => 10],
    ]);

    expect($medicine->currentStock())->toBe(10);
});

// ---------------------------------------------------------------------------
// Status stok
// ---------------------------------------------------------------------------

it('memperbarui status stok obat yang terdampak', function () {
    $medicine = makeMedicine(['min_stock' => 20]);
    $receiveOrder = makeReceiveOrder($medicine, 19);

    $this->service->recordReceipt($receiveOrder);
    $this->service->refreshStockStatus([$medicine->id]);

    expect($medicine->fresh()->stock_status)->toBe('almost_empty');
});
