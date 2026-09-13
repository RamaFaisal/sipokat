<?php

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\MedicineStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Models\Supplier;
use App\Models\Unit;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Fixture
|--------------------------------------------------------------------------
|
| Master data minimal yang dibutuhkan hampir semua tes. Dipanggil dari
| beforeEach() dan menaruh hasilnya di properti test case.
|
*/

function seedMasterFixtures(): void
{
    test()->unit = Unit::create(['name' => 'Strip', 'alias' => 'STR']);

    test()->category = MedicineCategories::create([
        'name' => 'Obat Bebas',
        'alias' => 'OBB',
        'description' => 'fixture',
    ]);

    test()->supplier = Supplier::create([
        'code' => 'PBF-001',
        'name' => 'PBF Fixture',
        'status' => 'active',
    ]);
}

function makeMedicine(array $overrides = []): Medicine
{
    static $seq = 0;
    $seq++;

    return Medicine::create(array_merge([
        'name' => 'OBAT FIXTURE ' . $seq . ' 500MG',
        'category_id' => test()->category->id,
        'unit_id' => test()->unit->id,
        'pack_unit_id' => test()->unit->id,
        'pack_size' => 1,
        'min_stock' => 20,
    ], $overrides));
}

function makePurchaseOrder(): PurchaseOrder
{
    static $seq = 0;
    $seq++;

    return PurchaseOrder::create([
        'po_number' => sprintf('PO-FIX-%04d', $seq),
        'supplier_id' => test()->supplier->id,
        'po_date' => now()->toDateString(),
    ]);
}

/** Penerimaan beserta itemnya, TANPA entri kartu stok. */
function makeReceiveOrder(
    Medicine $medicine,
    int $qty,
    ?string $expiredDate = null,
    string $batch = 'B-001',
    ?PurchaseOrder $purchaseOrder = null,
): ReceiveOrder {
    static $seq = 0;
    $seq++;

    $receiveOrder = ReceiveOrder::create([
        'receive_order_number' => sprintf('RO-FIX-%04d', $seq),
        'purchase_order_id' => $purchaseOrder?->id,
        'supplier_id' => test()->supplier->id,
        'invoice_number' => sprintf('INV-FIX-%04d', $seq),
        'receive_date' => now()->toDateString(),
    ]);

    ReceiveOrderItem::create([
        'receive_order_id' => $receiveOrder->id,
        'medicine_id' => $medicine->id,
        'medicine_name' => $medicine->name,
        'pack_unit_id' => $medicine->unit_id,
        'pack_size' => 1,
        'pack_qty' => $qty,
        'qty' => $qty,
        'price' => FIXTURE_PURCHASE_PRICE,
        'batch_number' => $batch,
        'expired_date' => $expiredDate ?? now()->addYear()->startOfMonth()->toDateString(),
    ]);

    return $receiveOrder;
}

/** Harga fixture: harga hidup di transaksi, bukan di master obat (rencana M4, M5). */
const FIXTURE_PURCHASE_PRICE = 5000;
const FIXTURE_SALE_PRICE = 7500;

/** Penjualan beserta itemnya, TANPA entri kartu stok. */
function makeOrder(Medicine $medicine, int $qty): Order
{
    static $seq = 0;
    $seq++;

    $order = Order::create([
        'order_code' => sprintf('ORD-FIX-%04d', $seq),
        'no_payment' => sprintf('PAY-FIX-%04d', $seq),
        'order_date' => now()->toDateString(),
        'grand_total' => $qty * FIXTURE_SALE_PRICE,
        'status' => 'paid',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'medicine_id' => $medicine->id,
        'medicine_name' => $medicine->name,
        'qty' => $qty,
        'price' => FIXTURE_SALE_PRICE,
        'total' => $qty * FIXTURE_SALE_PRICE,
    ]);

    return $order;
}

/** Penerimaan + entri kartu stok D — meniru CreateReceiveOrder::afterCreate(). */
function receiveInto(Medicine $medicine, int $qty, ?string $expiredDate = null, string $batch = 'B-001'): ReceiveOrder
{
    $receiveOrder = makeReceiveOrder($medicine, $qty, $expiredDate, $batch);

    MedicineStock::create([
        'medicine_id' => $medicine->id,
        'qty' => $qty,
        'type_account' => 'D',
        'date' => $receiveOrder->receive_date,
        'hpp' => FIXTURE_PURCHASE_PRICE,
        'receive_order_id' => $receiveOrder->id,
        'description' => 'Penerimaan fixture',
    ]);

    return $receiveOrder;
}

/** Penjualan + entri kartu stok C — meniru CreateOrder::handleRecordCreation(). */
function sellFrom(Medicine $medicine, int $qty): Order
{
    $order = makeOrder($medicine, $qty);

    MedicineStock::create([
        'medicine_id' => $medicine->id,
        'qty' => $qty,
        'type_account' => 'C',
        'date' => $order->order_date,
        'hpp' => FIXTURE_SALE_PRICE,
        'order_id' => $order->id,
        'description' => 'Penjualan fixture',
    ]);

    return $order;
}
