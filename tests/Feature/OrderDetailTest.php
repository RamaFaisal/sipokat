<?php

use App\Models\MedicineStock;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman lihat penjualan memakai infolist sendiri (butir 2). Sebelumnya halaman ini menumpang
 * skema form, sehingga Subtotal, Batch, dan Total selalu kosong: ketiganya medan bantu yang
 * `dehydrated(false)` dan hanya terisi saat mengetik di form tambah.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menampilkan total, subtotal, dan batch yang sesungguhnya terpakai', function () {
    $obat = makeMedicine(['name' => 'OBAT DETAIL 500MG']);
    receiveInto($obat, 10, today()->addMonths(6)->startOfMonth()->toDateString(), 'B-LAMA');
    receiveInto($obat, 10, today()->addMonths(18)->startOfMonth()->toDateString(), 'B-BARU');

    // 12 satuan: 10 dari batch ED terdekat, sisanya 2 dari batch berikutnya (FEFO).
    $order = sellFrom($obat, 12);

    $halaman = $this->get("/admin/orders/{$order->id}");

    $halaman->assertOk()
        ->assertSee($order->order_code)
        ->assertSee('B-LAMA')
        ->assertSee('B-BARU');
});

it('menampilkan catatan yang tersimpan', function () {
    $obat = makeMedicine();
    receiveInto($obat, 10);
    $order = makeOrder($obat, 2);
    $order->update(['note' => 'Pembeli minta kuitansi']);
    app(App\Services\StockMovementService::class)->recordSale($order);

    $this->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Pembeli minta kuitansi');
});

it('membaca batch dari kartu stok, bukan dari pratinjau stok hari ini', function () {
    $obat = makeMedicine();
    receiveInto($obat, 5, today()->addMonths(6)->startOfMonth()->toDateString(), 'B-HABIS');
    $order = sellFrom($obat, 5); // batch B-HABIS terpakai habis

    // Penerimaan sesudahnya: pratinjau FEFO hari ini akan menunjuk batch baru, bukan B-HABIS.
    receiveInto($obat, 20, today()->addMonths(24)->startOfMonth()->toDateString(), 'B-SETELAH');

    $this->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('B-HABIS')
        ->assertDontSee('B-SETELAH');
});

it('tidak memuat ulang alokasi untuk tiap baris item', function () {
    $obat = makeMedicine();
    $lain = makeMedicine(['name' => 'OBAT KEDUA 250MG']);
    receiveInto($obat, 10);
    receiveInto($lain, 10);

    $order = Order::create(['order_code' => 'ORD-DETAIL-1', 'order_date' => today()->toDateString(), 'grand_total' => 0]);
    foreach ([$obat, $lain] as $m) {
        $order->items()->create(['medicine_id' => $m->id, 'medicine_name' => $m->name, 'qty' => 2, 'price' => FIXTURE_SALE_PRICE]);
    }
    app(App\Services\StockMovementService::class)->recordSale($order);

    expect(MedicineStock::where('order_id', $order->id)->where('type_account', 'C')->count())->toBe(2);

    $this->get("/admin/orders/{$order->id}")->assertOk();
});
