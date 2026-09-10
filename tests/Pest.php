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
    test()->unit = Unit::create(['name' => 'Kapsul', 'alias' => 'KAP']);

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
        'code' => sprintf('SIP/FIX%03d/OBB/KAP/001', $seq),
        'name' => 'Obat Fixture ' . $seq,
        'dosage' => '500 mg',
        'category_id' => test()->category->id,
        'unit_id' => test()->unit->id,
        'purchase_price' => 5000,
        'sale_price' => 7500,
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
        'status' => 'approved',
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
        'receive_date' => now()->toDateString(),
        'status' => 'completed',
    ]);

    ReceiveOrderItem::create([
        'receive_order_id' => $receiveOrder->id,
        'medicine_id' => $medicine->id,
        'medicine_name' => $medicine->name,
        'qty' => $qty,
        'price' => $medicine->purchase_price,
        'batch_number' => $batch,
        'expired_date' => $expiredDate,
    ]);

    return $receiveOrder;
}

/** Penjualan beserta itemnya, TANPA entri kartu stok. */
function makeOrder(Medicine $medicine, int $qty): Order
{
    static $seq = 0;
    $seq++;

    $order = Order::create([
        'order_code' => sprintf('ORD-FIX-%04d', $seq),
        'no_payment' => sprintf('PAY-FIX-%04d', $seq),
        'order_date' => now()->toDateString(),
        'grand_total' => $qty * $medicine->sale_price,
        'status' => 'paid',
    ]);

    OrderItem::create([
        'order_id' => $order->id,
        'medicine_id' => $medicine->id,
        'medicine_name' => $medicine->name,
        'qty' => $qty,
        'price' => $medicine->sale_price,
        'total' => $qty * $medicine->sale_price,
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
        'hpp' => $medicine->purchase_price,
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
        'hpp' => $medicine->sale_price,
        'order_id' => $order->id,
        'description' => 'Penjualan fixture',
    ]);

    return $order;
}
