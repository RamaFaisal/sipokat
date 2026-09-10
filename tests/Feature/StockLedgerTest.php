<?php

/*
|--------------------------------------------------------------------------
| Tes karakterisasi kartu stok
|--------------------------------------------------------------------------
|
| Tes ini mengunci perilaku aritmetika stok APA ADANYA sebelum pekerjaan
| stok per batch & FEFO (CLAUDE.md Section 13) dimulai. Tujuannya bukan
| menyatakan perilaku sekarang sudah ideal, melainkan memastikan angka stok
| tidak bergeser diam-diam saat lapisan batch ditambahkan.
|
| Cakupan: sisi BACA (Medicine::currentStock, StockCardService). Helper
| fixture-nya ada di tests/Pest.php.
|
*/

use App\Services\StockCardService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-10 08:00:00'));

    seedMasterFixtures();
});

// ---------------------------------------------------------------------------
// Aritmetika dasar
// ---------------------------------------------------------------------------

it('menambah stok dari entri penerimaan', function () {
    $medicine = makeMedicine();

    receiveInto($medicine, 120);

    expect($medicine->currentStock())->toBe(120);
});

it('mengurangi stok dari entri penjualan', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 120);

    sellFrom($medicine, 45);

    expect($medicine->currentStock())->toBe(75);
});

it('menjumlahkan beberapa penerimaan dan penjualan', function () {
    $medicine = makeMedicine();

    receiveInto($medicine, 100);
    receiveInto($medicine, 50);
    sellFrom($medicine, 30);
    sellFrom($medicine, 20);

    expect($medicine->currentStock())->toBe(100);
});

it('menghasilkan angka yang sama antara currentStock dan getAvailableStock', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 90);
    sellFrom($medicine, 17);

    $service = app(StockCardService::class);

    expect($medicine->currentStock())->toBe(73)
        ->and((float) $service->getAvailableStock($medicine->id))->toBe(73.0);
});

// ---------------------------------------------------------------------------
// Cascade soft-delete
// ---------------------------------------------------------------------------

it('mengabaikan entri dari penjualan yang dihapus sementara', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 100);
    $order = sellFrom($medicine, 40);

    expect($medicine->currentStock())->toBe(60);

    $order->delete();

    expect($medicine->currentStock())->toBe(100);
});

it('mengabaikan entri dari penerimaan yang dihapus sementara', function () {
    $medicine = makeMedicine();
    $ro = receiveInto($medicine, 100);
    receiveInto($medicine, 25);

    expect($medicine->currentStock())->toBe(125);

    $ro->delete();

    expect($medicine->currentStock())->toBe(25);
});

it('memberi hasil cascade yang sama pada getAvailableStock', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 100);
    $order = sellFrom($medicine, 40);
    $order->delete();

    $service = app(StockCardService::class);

    expect((float) $service->getAvailableStock($medicine->id))->toBe(100.0)
        ->and($medicine->currentStock())->toBe(100);
});

// ---------------------------------------------------------------------------
// Label status stok
// ---------------------------------------------------------------------------

it('menandai stok kosong sebagai empty', function () {
    $medicine = makeMedicine(['min_stock' => 20]);
    $service = app(StockCardService::class);

    $service->updateMedicineStockStatus($medicine->id);

    expect($medicine->fresh()->stock_status)->toBe('empty');
});

it('menandai stok di bawah batas minimum sebagai almost_empty', function () {
    $medicine = makeMedicine(['min_stock' => 20]);
    receiveInto($medicine, 19);
    $service = app(StockCardService::class);

    $service->updateMedicineStockStatus($medicine->id);

    expect($medicine->fresh()->stock_status)->toBe('almost_empty');
});

it('menandai stok tepat di batas minimum sebagai available', function () {
    // Batasnya ketat (`<`), bukan (`<=`). Perhatikan Medicine::isLowStock()
    // memakai `<=` — dead code dengan definisi tandingan, lihat CLAUDE.md 13.6.
    $medicine = makeMedicine(['min_stock' => 20]);
    receiveInto($medicine, 20);
    $service = app(StockCardService::class);

    $service->updateMedicineStockStatus($medicine->id);

    expect($medicine->fresh()->stock_status)->toBe('available');
});

// ---------------------------------------------------------------------------
// Kedaluwarsa (sumber kriteria C3)
// ---------------------------------------------------------------------------

it('memakai batch dengan kedaluwarsa terdekat', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 50, now()->addDays(400)->toDateString(), 'B-LAMA');
    receiveInto($medicine, 50, now()->addDays(120)->toDateString(), 'B-DEKAT');

    expect($medicine->nearestExpiryDays())->toBe(120);
});

it('mengabaikan batch yang sudah lewat tanggal kedaluwarsanya', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 50, now()->subDays(10)->toDateString(), 'B-KADALUWARSA');
    receiveInto($medicine, 50, now()->addDays(200)->toDateString(), 'B-AKTIF');

    expect($medicine->nearestExpiryDays())->toBe(200);
});

it('tidak memberi tanggal kedaluwarsa saat stok habis', function () {
    $medicine = makeMedicine();
    receiveInto($medicine, 30, now()->addDays(100)->toDateString());
    sellFrom($medicine, 30);

    expect($medicine->currentStock())->toBe(0)
        ->and($medicine->nearestExpiryDate())->toBeNull();
});

it('masih membaca batch yang stoknya sudah habis terjual', function () {
    // KARAKTERISASI CACAT YANG AKAN DIPERBAIKI DI TAHAP 7.
    // Batch dekat (30 hari) sudah habis terjual, fisik yang tersisa hanya
    // batch jauh (400 hari) — tetapi nearestExpiryDate() tidak memeriksa sisa
    // per batch, sehingga C3 tetap membaca 30 hari.
    //
    // Setelah lapisan batch selesai, tes ini HARUS gagal dan diubah jadi 400.
    // Kegagalannya adalah bukti refactor berhasil.
    $medicine = makeMedicine();
    receiveInto($medicine, 10, now()->addDays(30)->toDateString(), 'B-DEKAT');
    receiveInto($medicine, 100, now()->addDays(400)->toDateString(), 'B-JAUH');

    sellFrom($medicine, 10);

    expect($medicine->currentStock())->toBe(100)
        ->and($medicine->nearestExpiryDays())->toBe(30);
});
