<?php

use App\Models\Medicine;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\ReceiveOrder;
use App\Models\User;
use Database\Seeders\FakturNpmSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\MedicineDataSeeder;
use Database\Seeders\PbfJatengSeeder;
use Database\Seeders\PurchaseOrderSeeder;
use Database\Seeders\SawCriteriaSeeder;
use Database\Seeders\SimulasiPenjualanSeeder;

/**
 * `DatabaseSeeder::run()` penuh (rencana-sidang-2026-10 §3): pada deploy baru, `db:seed`
 * harus membuat tiap menu langsung terisi tanpa berkas lokal (obat riil, faktur riil,
 * contoh PO, simulasi penjualan) dan aman dijalankan dua kali (idempoten).
 */
beforeEach(function () {
    User::factory()->create();
    $this->seed(MasterDataSeeder::class);
    $this->seed(PbfJatengSeeder::class);
    $this->seed(SawCriteriaSeeder::class);
});

it('memuat obat riil dari berkas seeder yang ikut ter-commit', function () {
    $this->seed(MedicineDataSeeder::class);

    expect(Medicine::count())->toBe(127);
});

it('menjalankan seluruh rantai seeder tanpa galat dan mengisi tiap menu', function () {
    $this->seed(MedicineDataSeeder::class);
    $this->seed(FakturNpmSeeder::class);
    $this->seed(PurchaseOrderSeeder::class);
    $this->seed(SimulasiPenjualanSeeder::class);

    expect(Medicine::count())->toBe(127)
        ->and(ReceiveOrder::count())->toBe(14)
        ->and(PurchaseOrder::count())->toBeGreaterThan(0)
        ->and(Order::count())->toBeGreaterThan(0);

    // Tidak ada obat yang terjual melebihi stok tersedianya.
    $stockCard = app(App\Services\StockCardService::class);
    foreach (Medicine::all() as $medicine) {
        expect($stockCard->availableStock($medicine->id))->toBeGreaterThanOrEqual(0);
    }
});

it('idempoten: diulang tidak melipatgandakan data', function () {
    $this->seed(MedicineDataSeeder::class);
    $this->seed(FakturNpmSeeder::class);
    $this->seed(PurchaseOrderSeeder::class);
    $this->seed(SimulasiPenjualanSeeder::class);

    $medicineCount = Medicine::count();
    $roCount = ReceiveOrder::count();
    $poCount = PurchaseOrder::count();
    $orderCount = Order::count();

    $this->seed(MedicineDataSeeder::class);
    $this->seed(FakturNpmSeeder::class);
    $this->seed(PurchaseOrderSeeder::class);
    $this->seed(SimulasiPenjualanSeeder::class);

    expect(Medicine::count())->toBe($medicineCount)
        ->and(ReceiveOrder::count())->toBe($roCount)
        ->and(PurchaseOrder::count())->toBe($poCount)
        ->and(Order::count())->toBe($orderCount);
});

it('menomori dokumen seeder sesuai tanggal dokumennya, bukan tanggal seeder dijalankan', function () {
    $this->seed(MedicineDataSeeder::class);
    $this->seed(FakturNpmSeeder::class);
    $this->seed(SimulasiPenjualanSeeder::class);

    // Penjualan simulasi tersebar ke belakang; nomornya harus ikut tanggal penjualan
    // supaya lampiran Bab IV tidak memperlihatkan dokumen bertanggal Mei bernomor September.
    Order::query()->get(['order_code', 'order_date'])->each(function (Order $order) {
        expect(substr($order->order_code, strlen(Order::CODE_PREFIX), 8))
            ->toBe($order->order_date->format('Ymd'));
    });

    ReceiveOrder::query()->get(['receive_order_number', 'receive_date'])->each(function (ReceiveOrder $ro) {
        expect(substr($ro->receive_order_number, strlen(ReceiveOrder::CODE_PREFIX), 8))
            ->toBe($ro->receive_date->format('Ymd'));
    });
});
