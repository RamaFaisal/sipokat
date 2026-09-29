<?php

use App\Filament\Pages\LaporanKedaluwarsa;
use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Models\MedicineStock;
use App\Models\User;
use App\Support\BatchBersisa;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Batch yang dibeli dua kali tetap satu tumpukan di rak, jadi tampilannya digabung per **nomor
 * batch**. Yang tidak ikut digabung: kartu stok, karena itu buku besar yang mencatat satu baris
 * per dokumen, dan alokasi FEFO yang tetap menyentuh lapisan aslinya.
 *
 * Pengelompokan memakai nomor batch, bukan tanggal kedaluwarsa: dua batch berbeda bisa kebetulan
 * kedaluwarsa pada bulan yang sama, dan menyatukannya menghapus identitas yang dipakai saat retur.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menjumlahkan sisa dua penerimaan dengan batch sama menjadi satu baris', function () {
    $obat = makeMedicine(['name' => 'OBAT BATCH KEMBAR']);
    $ed = today()->addDays(60)->toDateString();

    receiveInto($obat, 56, $ed, 'B-KEMBAR');
    sellFrom($obat, 18);
    receiveInto($obat, 28, $ed, 'B-KEMBAR');

    $baris = BatchBersisa::query()->get();

    expect($baris)->toHaveCount(1)
        ->and((int) $baris->first()->sisa)->toBe(66)          // (56 - 18) + 28
        ->and((int) $baris->first()->jumlah_lapisan)->toBe(2)
        ->and((float) $baris->first()->nilai)->toBe(66 * (float) FIXTURE_PURCHASE_PRICE);
});

it('tidak menyatukan dua batch berbeda yang kedaluwarsa di bulan sama', function () {
    $obat = makeMedicine(['name' => 'OBAT ED SAMA']);
    $ed = today()->addDays(45)->toDateString();

    receiveInto($obat, 10, $ed, 'B-SATU');
    receiveInto($obat, 10, $ed, 'B-DUA');

    $baris = BatchBersisa::query()->get();

    expect($baris)->toHaveCount(2)
        ->and($baris->pluck('batch_number')->sort()->values()->all())->toBe(['B-DUA', 'B-SATU']);
});

it('membuang batch yang sudah habis terjual', function () {
    $obat = makeMedicine();
    receiveInto($obat, 10, today()->addDays(30)->toDateString(), 'B-HABIS');
    receiveInto($obat, 10, today()->addDays(300)->toDateString(), 'B-SISA');
    sellFrom($obat, 10); // FEFO menghabiskan B-HABIS

    $batch = BatchBersisa::query()->get();

    expect($batch)->toHaveCount(1)
        ->and($batch->first()->batch_number)->toBe('B-SISA');
});

it('menampilkan satu baris per obat di widget kedaluwarsa, memakai ED terdekat', function () {
    // Kolom widget hanya nama dan sisa hari. Dua batch berbeda dengan ED sama akan tampil sebagai
    // dua baris yang tidak bisa dibedakan, jadi widget dikelompokkan per obat.
    $obat = makeMedicine(['name' => 'OBAT WIDGET DUA BATCH']);

    receiveInto($obat, 40, today()->addDays(50)->toDateString(), 'B-DEKAT');
    receiveInto($obat, 20, today()->addDays(400)->toDateString(), 'B-JAUH');

    $render = Livewire::test(ExpiringMedicinesWidget::class)->assertOk()->html();

    expect(substr_count($render, 'OBAT WIDGET DUA BATCH'))->toBe(1);

    $baris = App\Support\BatchBersisa::perObat()->where('medicine_id', $obat->id)->first();

    expect((int) $baris->sisa)->toBe(60)
        ->and((int) $baris->jumlah_batch)->toBe(2)
        ->and(App\Support\BatchBersisa::sisaHari($baris))->toBe(50);
});

it('menampilkan batch kembar sebagai satu baris di laporan kedaluwarsa', function () {
    $obat = makeMedicine(['name' => 'OBAT LAPORAN KEMBAR']);
    $ed = today()->addDays(50)->toDateString();

    receiveInto($obat, 40, $ed, 'B-LAPORAN');
    receiveInto($obat, 20, $ed, 'B-LAPORAN');

    $render = Livewire::test(LaporanKedaluwarsa::class)->assertOk()->html();

    expect(substr_count($render, 'B-LAPORAN'))->toBe(1);
});

it('tetap mencatat dua lapisan terpisah di kartu stok', function () {
    $obat = makeMedicine();
    $ed = today()->addDays(50)->toDateString();

    receiveInto($obat, 40, $ed, 'B-LEDGER');
    receiveInto($obat, 20, $ed, 'B-LEDGER');

    // Penggabungan hanya di tampilan; buku besarnya tetap satu baris per dokumen.
    expect(MedicineStock::layers()->where('medicine_id', $obat->id)->where('batch_number', 'B-LEDGER')->count())
        ->toBe(2);
});

it('menampilkan satu chip FEFO untuk batch yang dibeli dua kali', function () {
    $obat = makeMedicine(['name' => 'OBAT CHIP KEMBAR']);
    $ed = today()->addDays(120)->toDateString();

    receiveInto($obat, 6, $ed, 'B-CHIP');
    receiveInto($obat, 6, $ed, 'B-CHIP');

    $halaman = Livewire::test(App\Filament\Resources\Orders\Pages\CreateOrder::class);
    $kunci = firstRowKey($halaman, 'items');
    $halaman->fillForm(['order_date' => today()->toDateString()]
        + firstRow($halaman, 'items', ['medicine_id' => $obat->id, 'qty' => 9, 'price' => FIXTURE_SALE_PRICE]));

    // 9 satuan diambil dari dua lapisan, tetapi kasir melihat satu batch: 9 dari 12.
    expect($halaman->get("data.items.{$kunci}.fefo_batches"))->toBe(['B-CHIP:9']);
});

it('membungkus pengelompokan sebagai subquery agar lolos ONLY_FULL_GROUP_BY', function () {
    // Tabel Filament menambahkan `order by medicine_stocks.id` sebagai pemecah seri. Pada MySQL
    // ber-ONLY_FULL_GROUP_BY itu ditolak bila query-nya GROUP BY biasa, karena `id` bukan kolom
    // yang dikelompokkan. Membungkusnya sebagai tabel turunan membuat `id` jadi kolom biasa.
    //
    // SQLite tidak seketat itu, jadi kesalahan ini tidak akan muncul sebagai galat di suite tes.
    // Yang dijaga di sini bentuk query-nya, bukan galatnya.
    $sql = BatchBersisa::query()->toSql();

    expect($sql)->toContain('from (select')
        ->and($sql)->not->toStartWith('select min(');
});

it('tetap benar saat diurutkan seperti tabel Filament', function () {
    $obat = makeMedicine();
    receiveInto($obat, 10, today()->addDays(20)->toDateString(), 'B-A');
    receiveInto($obat, 10, today()->addDays(40)->toDateString(), 'B-B');

    $baris = BatchBersisa::query()
        ->orderBy('expired_date')
        ->orderBy('medicine_stocks.id')
        ->get();

    expect($baris->pluck('batch_number')->all())->toBe(['B-A', 'B-B']);
});
