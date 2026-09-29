<?php

use App\Filament\Pages\LaporanKedaluwarsa;
use App\Filament\Pages\LaporanMoving;
use App\Filament\Pages\LaporanOpname;
use App\Filament\Pages\LaporanPembelianPbf;
use App\Filament\Pages\LaporanRekap;
use App\Filament\Pages\LaporanStokObat;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Models\User;
use App\Support\AmbangEd;
use App\Support\MenuLaporan;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menu Laporan bertab dengan empat laporan baru (butir 7 / K7).
 *
 * Yang diuji bukan sekadar "halaman terbuka", melainkan angkanya: laporan yang tampil tapi salah
 * hitung lebih berbahaya daripada laporan yang tidak ada.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('memuat seluruh tab laporan', function (string $kelas) {
    $this->get($kelas::getUrl())->assertOk();
})->with([
    'rekap' => LaporanRekap::class,
    'moving' => LaporanMoving::class,
    'kedaluwarsa' => LaporanKedaluwarsa::class,
    'stok per obat' => LaporanStokObat::class,
    'pembelian per PBF' => LaporanPembelianPbf::class,
    'hasil opname' => LaporanOpname::class,
]);

it('menandai tab yang sedang dibuka dan menyembunyikan yang tidak boleh diakses', function () {
    $tabs = MenuLaporan::untukPengguna(LaporanKedaluwarsa::class);

    expect($tabs)->toHaveCount(6)
        ->and(collect($tabs)->firstWhere('aktif', true)['label'])->toBe('Akan Kedaluwarsa');
});

it('menghitung nilai terancam dari sisa batch dikali HPP-nya', function () {
    $obat = makeMedicine(['name' => 'OBAT MEPET ED']);
    // Harga beli fixture 5.000; 10 diterima, 4 terjual, sisa 6 batch mepet.
    receiveInto($obat, 10, today()->addDays(20)->toDateString(), 'B-MEPET');
    sellFrom($obat, 4);

    Livewire::test(LaporanKedaluwarsa::class)
        ->assertOk()
        ->assertSee('B-MEPET');

    // Nilai terancam = sisa lapisan x HPP baris itu; diperiksa pada angkanya, bukan pada
    // format rupiah yang bisa berubah mengikuti locale.
    $lapisan = App\Models\MedicineStock::layers()
        ->where('medicine_id', $obat->id)
        ->withSum('consumptions', 'qty')
        ->first();

    expect($lapisan->remaining)->toBe(6)
        ->and($lapisan->remaining * (float) $lapisan->hpp)->toBe(30000.0);
});

it('tidak memuat batch di luar ambang pantauan', function () {
    $obat = makeMedicine(['name' => 'OBAT ED JAUH']);
    receiveInto($obat, 10, today()->addDays(AmbangEd::PANTAU + 60)->toDateString(), 'B-JAUH');

    Livewire::test(LaporanKedaluwarsa::class)
        ->assertOk()
        ->assertDontSee('B-JAUH');
});

it('merekap stok awal, masuk, keluar, dan akhir per obat', function () {
    $obat = makeMedicine(['name' => 'OBAT REKAP STOK']);
    receiveInto($obat, 100);
    sellFrom($obat, 30);

    $halaman = Livewire::test(LaporanStokObat::class);
    $baris = collect($halaman->get('rows'))->firstWhere('name', 'OBAT REKAP STOK');

    // Keduanya di dalam periode berjalan, jadi stok awal 0.
    expect($baris['awal'])->toBe(0)
        ->and($baris['masuk'])->toBe(100)
        ->and($baris['keluar'])->toBe(30)
        ->and($baris['akhir'])->toBe(70);
});

it('memindahkan mutasi sebelum periode menjadi stok awal', function () {
    $obat = makeMedicine(['name' => 'OBAT SALDO AWAL']);
    $ro = receiveInto($obat, 50);
    // Penerimaan digeser ke bulan lalu, di luar periode bawaan (bulan berjalan).
    $ro->update(['receive_date' => today()->subMonth()->toDateString()]);
    App\Models\MedicineStock::where('receive_order_id', $ro->id)->update(['date' => today()->subMonth()->toDateString()]);

    $halaman = Livewire::test(LaporanStokObat::class);
    $baris = collect($halaman->get('rows'))->firstWhere('name', 'OBAT SALDO AWAL');

    expect($baris['awal'])->toBe(50)
        ->and($baris['masuk'])->toBe(0)
        ->and($baris['akhir'])->toBe(50);
});

it('mengelompokkan nilai pembelian per PBF beserta porsinya', function () {
    $obat = makeMedicine();
    receiveInto($obat, 10); // 10 x 5.000 = 50.000 ke PBF fixture

    $halaman = Livewire::test(LaporanPembelianPbf::class);
    $baris = collect($halaman->get('rows'))->first();

    expect($baris['nilai'])->toBe(50000.0)
        ->and($baris['faktur'])->toBe(1)
        ->and($baris['ragam_obat'])->toBe(1)
        ->and($halaman->instance()->porsi($baris['nilai']))->toBe(100.0);
});

it('menampilkan selisih opname beserta arah dan nilainya', function () {
    $obat = makeMedicine(['name' => 'OBAT OPNAME LAPOR']);
    receiveInto($obat, 20);

    $opname = MedicineStockOpname::create([
        'opname_number' => 'OPM-LAPOR-1',
        'opname_date' => today()->toDateString(),
    ]);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id,
        'medicine_id' => $obat->id,
        'qty' => 3,
        'type_account' => 'C',
        'hpp' => 5000,
        'note' => 'rusak kemasan',
    ]);

    Livewire::test(LaporanOpname::class)
        ->assertOk()
        ->assertSee('OPM-LAPOR-1')
        ->assertSee('rusak kemasan')
        ->assertSee('Kurang');
});

/**
 * Keseragaman enam tab Laporan (2026-09-29).
 *
 * Empat laporan baru sempat berbeda bentuk dari dua laporan lama: tabel Filament dengan filter
 * sendiri, hanya ekspor Excel, tanpa ringkasan. Yang dijaga di bawah ini adalah ketiganya sekarang
 * sama: nama aksi header, adanya ringkasan, dan ekspor Excel maupun PDF yang benar-benar jadi
 * berkas.
 */
it('menyediakan tiga aksi header yang sama di seluruh tab laporan', function (string $kelas) {
    Livewire::test($kelas)
        ->assertActionExists('generate')
        ->assertActionExists('export')
        ->assertActionExists('exportPdf');
})->with([
    'rekap' => LaporanRekap::class,
    'moving' => LaporanMoving::class,
    'kedaluwarsa' => LaporanKedaluwarsa::class,
    'stok per obat' => LaporanStokObat::class,
    'pembelian per PBF' => LaporanPembelianPbf::class,
    'hasil opname' => LaporanOpname::class,
]);

it('mencetak ringkasan yang sama dengan isi tabelnya', function () {
    $obat = makeMedicine(['name' => 'OBAT RINGKAS ED']);
    receiveInto($obat, 10, today()->addDays(20)->toDateString(), 'B-RINGKAS');
    sellFrom($obat, 4);

    $halaman = Livewire::test(LaporanKedaluwarsa::class);
    $ringkasan = $halaman->instance()->ringkasan();

    // Sisa 6 x HPP 5.000 = 30.000, angka yang sama dengan kolom Nilai Terancam.
    expect($ringkasan['Jumlah Batch'])->toBe('1')
        ->and($ringkasan['Nilai Terancam'])->toBe('Rp 30.000')
        ->and($ringkasan['Mendesak (≤ '.AmbangEd::MENDESAK.' Hari)'])->toBe('1 batch');

    $halaman->assertSee('Rp 30.000');
});

it('menyaring laporan kedaluwarsa menurut rentang dan tingkat yang dipilih', function () {
    $mepet = makeMedicine(['name' => 'OBAT MEPET']);
    receiveInto($mepet, 5, today()->addDays(10)->toDateString(), 'B-MEPET-2');

    $jauh = makeMedicine(['name' => 'OBAT JAUH']);
    receiveInto($jauh, 5, today()->addDays(200)->toDateString(), 'B-JAUH-2');

    $halaman = Livewire::test(LaporanKedaluwarsa::class);

    // Bawaan 90 hari: hanya yang mepet.
    $halaman->assertSee('B-MEPET-2')->assertDontSee('B-JAUH-2');

    // Rentang 365 hari memuat keduanya.
    $halaman->set('data.horizon', 365)->call('generate')
        ->assertSee('B-MEPET-2')
        ->assertSee('B-JAUH-2');

    // Tingkat "waspada" (31 sampai 60 hari) menyingkirkan keduanya.
    $halaman->set('data.tingkat', 'waspada')->call('generate')
        ->assertDontSee('B-MEPET-2')
        ->assertDontSee('B-JAUH-2');
});

it('membatasi laporan opname pada periode dan arah yang dipilih', function () {
    $obat = makeMedicine(['name' => 'OBAT OPNAME FILTER']);
    receiveInto($obat, 20);

    $opname = MedicineStockOpname::create([
        'opname_number' => 'OPM-FILTER-1',
        'opname_date' => today()->toDateString(),
    ]);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id,
        'medicine_id' => $obat->id,
        'qty' => 2,
        'type_account' => 'C',
        'hpp' => 5000,
    ]);

    $halaman = Livewire::test(LaporanOpname::class);
    $halaman->assertSee('OPM-FILTER-1');

    // Arah "Lebih" tidak memuat penyesuaian kurang.
    $halaman->set('data.arah', 'D')->call('generate')->assertDontSee('OPM-FILTER-1');

    // Periode yang seluruhnya sebelum opname juga tidak memuatnya.
    $halaman->set('data.arah', 'semua')
        ->set('data.period_start', today()->subDays(60)->toDateString())
        ->set('data.period_end', today()->subDays(30)->toDateString())
        ->call('generate')
        ->assertDontSee('OPM-FILTER-1');

    expect($halaman->instance()->ringkasan()['Baris Penyesuaian'])->toBe('0');
});

it('mengunduh Excel dan PDF dari tiap laporan baru', function (string $kelas) {
    $obat = makeMedicine(['name' => 'OBAT EKSPOR']);
    receiveInto($obat, 10, today()->addDays(20)->toDateString(), 'B-EKSPOR');

    $opname = MedicineStockOpname::create([
        'opname_number' => 'OPM-EKSPOR-1',
        'opname_date' => today()->toDateString(),
    ]);
    MedicineStockOpnameItem::create([
        'medicine_stock_opname_id' => $opname->id,
        'medicine_id' => $obat->id,
        'qty' => 1,
        'type_account' => 'D',
        'hpp' => 5000,
    ]);

    $halaman = Livewire::test($kelas)->instance();

    expect($halaman->exportExcel())->toBeInstanceOf(StreamedResponse::class)
        ->and($halaman->exportPdf())->toBeInstanceOf(StreamedResponse::class);
})->with([
    'kedaluwarsa' => LaporanKedaluwarsa::class,
    'stok per obat' => LaporanStokObat::class,
    'pembelian per PBF' => LaporanPembelianPbf::class,
    'hasil opname' => LaporanOpname::class,
]);
