<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Smoke test: halaman panel yang tersentuh revisi bisa dirender tanpa error
 * (kolom yang dihapus tidak lagi dirujuk form/table). Bukan uji perilaku
 * maupun otorisasi — policy Shield dilewati.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('merender halaman obat', function (string $path) {
    makeMedicine();

    $this->get('/admin/'.$path)->assertOk();
})->with([
    'daftar' => 'medicines',
    'buat' => 'medicines/create',
]);

it('merender halaman edit obat', function () {
    $m = makeMedicine();

    $this->get("/admin/medicines/{$m->id}/edit")->assertOk();
});

it('merender form transaksi yang memakai daftar obat', function (string $path) {
    makeMedicine();

    $this->get('/admin/'.$path)->assertOk();
})->with([
    'penjualan' => 'orders/create',
    'PO' => 'purchase-orders/create',
    'RO' => 'receive-orders/create',
    'opname' => 'medicine-stock-opnames/create',
]);

it('merender daftar dan edit pengadaan (PO & RO) serta dashboard', function () {
    $m = makeMedicine();
    $po = makePurchaseOrder();
    \App\Models\PurchaseOrderItem::create([
        'purchase_order_id' => $po->id, 'medicine_id' => $m->id, 'pack_unit_id' => $m->pack_unit_id,
        'pack_size' => 1, 'pack_qty' => 10, 'qty' => 10, 'price' => 1000,
    ]);
    $ro = receiveInto($m, 5);

    $this->get('/admin/purchase-orders')->assertOk();
    $this->get("/admin/purchase-orders/{$po->id}/edit")->assertOk();
    $this->get('/admin/receive-orders')->assertOk();
    $this->get("/admin/receive-orders/{$ro->id}/edit")->assertOk();
    $this->get('/admin')->assertOk();
});

it('merender daftar dan detail penjualan (tanpa halaman edit)', function () {
    $m = makeMedicine();
    receiveInto($m, 10);
    $order = sellFrom($m, 3);

    $this->get('/admin/orders')->assertOk();
    $this->get("/admin/orders/{$order->id}")->assertOk();
    $this->get("/admin/orders/{$order->id}/edit")->assertNotFound();
    $this->get('/admin/medicine-stock-opnames')->assertOk();
});

it('merender halaman SPK: kriteria, hitung prioritas, riwayat', function () {
    $this->seed(\Database\Seeders\SawCriteriaSeeder::class);
    $m = makeMedicine();
    receiveInto($m, 10);
    $calc = app(\App\Services\SawCalculationService::class)->execute(today()->subDays(29), today());

    $this->get('/admin/saw-criterias')->assertOk();
    $this->get('/admin/saw-calculation')->assertOk();
    $this->get('/admin/saw-calculations')->assertOk();
    $this->get("/admin/saw-calculations/{$calc->id}")->assertOk();
});
