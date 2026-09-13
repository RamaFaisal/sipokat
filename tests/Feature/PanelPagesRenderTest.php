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
