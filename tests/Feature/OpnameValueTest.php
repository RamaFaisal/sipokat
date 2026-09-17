<?php

use App\Models\MedicineStock;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\StockCardService;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

function opnameWith(array $items): MedicineStockOpname
{
    $op = MedicineStockOpname::create(['opname_number' => 'OPN-'.uniqid(), 'opname_date' => today()->toDateString(), 'status' => 'in_stock', 'created_by' => auth()->id()]);
    foreach ($items as $item) {
        MedicineStockOpnameItem::create($item + ['medicine_stock_opname_id' => $op->id]);
    }
    app(StockMovementService::class)->recordOpname($op);

    return $op;
}

it('menerima baris kurang lalu tambah untuk obat yang sama dalam satu opname (HPP dibaca sebelum baris ditulis)', function () {
    $m = makeMedicine(['min_stock' => 10]);
    receiveInto($m, 100, today()->addYear()->toDateString());
    $layer = app(StockCardService::class)->layers($m->id)->first();

    $op = opnameWith([
        ['medicine_id' => $m->id, 'layer_stock_id' => $layer->id, 'qty' => 50, 'type_account' => 'C', 'hpp' => FIXTURE_PURCHASE_PRICE, 'note' => 'Rusak'],
        ['medicine_id' => $m->id, 'batch_number' => 'BARU', 'expired_date' => today()->addMonths(6)->startOfMonth()->toDateString(), 'qty' => 10, 'type_account' => 'D', 'hpp' => FIXTURE_PURCHASE_PRICE],
    ]);

    $sc = app(StockCardService::class);
    expect($sc->physicalStock($m->id))->toBe(60)
        ->and($sc->layers($m->id)->count())->toBe(2)
        ->and(MedicineStock::whereNull('hpp_avg')->count())->toBe(0)
        ->and(MedicineStock::where('medicine_stock_opname_id', $op->id)->pluck('description')->first())->toContain('Rusak');
});

it('memberi HPP saat itu pada semua baris C penjualan meski satu obat muncul dua kali', function () {
    $m = makeMedicine(['min_stock' => 10]);
    receiveInto($m, 100, today()->addYear()->toDateString());

    $order = Order::create(['order_code' => 'ORD-T', 'order_date' => today()->toDateString(), 'grand_total' => 0, 'created_by' => auth()->id()]);
    OrderItem::create(['order_id' => $order->id, 'medicine_id' => $m->id, 'medicine_name' => $m->name, 'qty' => 3, 'price' => FIXTURE_SALE_PRICE]);
    OrderItem::create(['order_id' => $order->id, 'medicine_id' => $m->id, 'medicine_name' => $m->name, 'qty' => 2, 'price' => FIXTURE_SALE_PRICE]);
    app(StockMovementService::class)->recordSale($order);

    expect(MedicineStock::where('order_id', $order->id)->pluck('hpp')->map(fn ($h) => (int) $h)->all())->toBe([FIXTURE_PURCHASE_PRICE, FIXTURE_PURCHASE_PRICE]);
});

it('menampilkan nilai penyesuaian bertanda dan selisih bersih di detail opname', function () {
    $m = makeMedicine(['min_stock' => 10]);
    receiveInto($m, 100, today()->addYear()->toDateString());
    $layer = app(StockCardService::class)->layers($m->id)->first();
    $op = opnameWith([
        ['medicine_id' => $m->id, 'layer_stock_id' => $layer->id, 'qty' => 50, 'type_account' => 'C', 'hpp' => 175, 'note' => 'Rusak'],
        ['medicine_id' => $m->id, 'layer_stock_id' => $layer->id, 'qty' => 10, 'type_account' => 'D', 'hpp' => 175, 'note' => 'Ditemukan'],
    ]);

    $html = $this->get("/admin/medicine-stock-opnames/{$op->id}")->assertOk()->getContent();
    foreach (['− Rp 8.750', '+ Rp 1.750', '− Rp 7.000', 'masuk Rp 1.750 · keluar Rp 8.750', 'Nilai selisih bersih', 'HPP / satuan jual'] as $s) {
        expect($html)->toContain($s);
    }
    expect($html)->not->toContain('Total HPP');
});
