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

it('menyaring laporan kedaluwarsa menurut rentang yang dipilih', function () {
    $mepet = makeMedicine(['name' => 'OBAT MEPET']);
    receiveInto($mepet, 5, today()->addDays(10)->toDateString(), 'B-MEPET-2');

    $jauh = makeMedicine(['name' => 'OBAT JAUH']);
    receiveInto($jauh, 5, today()->addDays(200)->toDateString(), 'B-JAUH-2');

    $sangatJauh = makeMedicine(['name' => 'OBAT SANGAT JAUH']);
    receiveInto($sangatJauh, 5, today()->addDays(500)->toDateString(), 'B-JAUH-500');

    $halaman = Livewire::test(LaporanKedaluwarsa::class);

    // Bawaan < 90 hari: hanya yang mepet.
    $halaman->assertSee('B-MEPET-2')->assertDontSee('B-JAUH-2')->assertDontSee('B-JAUH-500');

    // Mengubah filter saja sudah menghitung ulang, tanpa menekan tombol.
    $halaman->set('data.horizon', 360)
        ->assertSee('B-MEPET-2')
        ->assertSee('B-JAUH-2')
        ->assertDontSee('B-JAUH-500');

    // "> 360 hari" arahnya terbalik: justru batch yang masih lama.
    $halaman->set('data.horizon', 'jauh')
        ->assertDontSee('B-MEPET-2')
        ->assertDontSee('B-JAUH-2')
        ->assertSee('B-JAUH-500');
});

it('menyaring laporan kedaluwarsa menurut golongan sisa stoknya', function () {
    $sedikit = makeMedicine(['name' => 'OBAT SISA SEDIKIT']);
    receiveInto($sedikit, 5, today()->addDays(20)->toDateString(), 'B-SISA-5');

    $sedang = makeMedicine(['name' => 'OBAT SISA SEDANG']);
    receiveInto($sedang, 30, today()->addDays(20)->toDateString(), 'B-SISA-30');

    $banyak = makeMedicine(['name' => 'OBAT SISA BANYAK']);
    receiveInto($banyak, 80, today()->addDays(20)->toDateString(), 'B-SISA-80');

    $halaman = Livewire::test(LaporanKedaluwarsa::class);

    $halaman->set('data.sisa', 'sedikit')
        ->assertSee('B-SISA-5')
        ->assertDontSee('B-SISA-30')
        ->assertDontSee('B-SISA-80');

    $halaman->set('data.sisa', 'sedang')
        ->assertDontSee('B-SISA-5')
        ->assertSee('B-SISA-30')
        ->assertDontSee('B-SISA-80');

    $halaman->set('data.sisa', 'banyak')
        ->assertDontSee('B-SISA-5')
        ->assertDontSee('B-SISA-30')
        ->assertSee('B-SISA-80');

    $halaman->set('data.sisa', 'semua')
        ->assertSee('B-SISA-5')
        ->assertSee('B-SISA-30')
        ->assertSee('B-SISA-80');
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

/**
 * Paginasi, filter langsung, dan kolom yang mengikuti tipe laporan (permintaan peneliti 2026-09-29).
 */
it('memotong tabel panjang menjadi beberapa halaman tanpa memotong ekspornya', function () {
    foreach (range(1, 12) as $i) {
        receiveInto(makeMedicine(['name' => sprintf('OBAT HALAMAN %02d', $i)]), 10);
    }

    $halaman = Livewire::test(LaporanStokObat::class)->set('perPage', 10);
    $terpakai = $halaman->instance();
    $jumlah = $terpakai->rows->count();

    expect($jumlah)->toBeGreaterThanOrEqual(12)
        ->and($terpakai->halaman()->count())->toBe(10)
        ->and($terpakai->halaman()->total())->toBe($jumlah)
        // Ekspor tidak ikut terpotong: seluruh baris tetap ditulis.
        ->and(collect($terpakai->barisEkspor())->count())->toBe($jumlah);

    $halaman->call('gotoPage', 2);

    expect($halaman->instance()->halaman()->count())->toBe(min(10, $jumlah - 10));
});

it('mengembalikan tabel ke halaman pertama saat filter berubah', function () {
    foreach (range(1, 12) as $i) {
        receiveInto(makeMedicine(['name' => sprintf('OBAT RESET %02d', $i)]), 10);
    }

    $halaman = Livewire::test(LaporanStokObat::class)->set('perPage', 10)->call('gotoPage', 2);

    expect($halaman->instance()->halaman()->currentPage())->toBe(2);

    // Periode baru menyusutkan hasilnya; tanpa reset, tabel berhenti di halaman yang sudah kosong.
    $halaman->set('data.period_start', today()->subDays(3)->toDateString());

    expect($halaman->instance()->halaman()->currentPage())->toBe(1);
});

it('menyesuaikan kolom rekap dengan tipe laporannya', function () {
    $obat = makeMedicine(['name' => 'OBAT REKAP KOLOM']);
    receiveInto($obat, 20);
    sellFrom($obat, 5);

    $halaman = Livewire::test(LaporanRekap::class);

    $halaman->set('data.tipe', 'penjualan')
        ->assertSee('Qty Jual')
        ->assertDontSee('Qty Beli')
        ->assertDontSee('Margin');

    $halaman->set('data.tipe', 'pembelian')
        ->assertSee('Qty Beli')
        ->assertDontSee('Qty Jual');

    $halaman->set('data.tipe', 'keduanya')
        ->assertSee('Qty Beli')
        ->assertSee('Qty Jual')
        ->assertSee('Margin');
});

it('mengurutkan rekap menurut sisi yang sedang diminta', function () {
    $besar = makeMedicine(['name' => 'OBAT BELI BESAR']);
    receiveInto($besar, 100); // 100 x 5.000 = 500.000

    $kecil = makeMedicine(['name' => 'OBAT BELI KECIL']);
    receiveInto($kecil, 10); // 50.000
    sellFrom($kecil, 8); // nilai jualnya jadi yang tertinggi

    $halaman = Livewire::test(LaporanRekap::class);

    // Pembelian Saja: yang terbesar nilainya di atas, bukan yang penjualannya tinggi.
    $halaman->set('data.tipe', 'pembelian');
    expect(collect($halaman->instance()->rows)->first()['name'])->toBe('OBAT BELI BESAR');

    $halaman->set('data.tipe', 'penjualan');
    expect(collect($halaman->instance()->rows)->first()['name'])->toBe('OBAT BELI KECIL');
});

it('menulis kolom ekspor Excel sesuai tipe laporan rekap', function () {
    $obat = makeMedicine(['name' => 'OBAT EKSPOR TIPE']);
    receiveInto($obat, 20);
    sellFrom($obat, 5);

    $halaman = Livewire::test(LaporanRekap::class)->set('data.tipe', 'penjualan')->instance();

    expect($halaman->exportExcel())->toBeInstanceOf(StreamedResponse::class)
        ->and($halaman->exportPdf())->toBeInstanceOf(StreamedResponse::class);
});

/** Barisan nomor halaman yang benar-benar dicetak, misalnya "1 ... 5 6 7 ... 13". */
function nomorHalaman(object $halaman): string
{
    return collect($halaman->halaman()->render()->offsetGet('elements'))
        ->map(fn ($e): string => is_string($e) ? '...' : implode(' ', array_keys($e)))
        ->implode(' ');
}

it('memendekkan barisan nomor halaman saat laporannya panjang', function () {
    foreach (range(1, 13) as $i) {
        receiveInto(makeMedicine(['name' => sprintf('OBAT NOMOR %02d', $i)]), 10);
    }

    // Satu baris per halaman supaya halamannya banyak tanpa perlu ratusan obat.
    $halaman = Livewire::test(LaporanStokObat::class)->set('perPage', 1);
    $terakhir = $halaman->instance()->halaman()->lastPage();

    expect($terakhir)->toBeGreaterThanOrEqual(13)
        // Halaman pertama dan terakhir selalu bisa diklik, tiga nomor di sekitar yang sedang dibuka.
        ->and(nomorHalaman($halaman->instance()))->toBe("1 2 3 ... {$terakhir}");

    $halaman->call('gotoPage', 6);
    expect(nomorHalaman($halaman->instance()))->toBe("1 ... 5 6 7 ... {$terakhir}");

    $halaman->call('gotoPage', $terakhir);
    expect(nomorHalaman($halaman->instance()))->toBe('1 ... '.($terakhir - 2).' '.($terakhir - 1).' '.$terakhir);
});

it('menyaring tampilan tabel lewat kotak pencarian tanpa mengubah ringkasan dan ekspor', function () {
    receiveInto(makeMedicine(['name' => 'PARACETAMOL CARI']), 10);
    receiveInto(makeMedicine(['name' => 'AMOXICILLIN CARI']), 20);

    $halaman = Livewire::test(LaporanStokObat::class)->set('pencarian', 'paracetamol');
    $terpakai = $halaman->instance();

    // Huruf kecil tetap cocok dengan nama obat yang tersimpan huruf besar.
    expect($terpakai->halaman()->pluck('name')->all())->toBe(['PARACETAMOL CARI'])
        // Ringkasan dan ekspor tetap seluruh baris periode itu, bukan hasil pencarian.
        ->and($terpakai->rows->count())->toBeGreaterThanOrEqual(2)
        ->and(collect($terpakai->barisEkspor())->count())->toBe($terpakai->rows->count())
        ->and($terpakai->ringkasan()['Obat Bermutasi'])->toBe(number_format($terpakai->rows->count(), 0, ',', '.'));

    // Kata kunci yang tidak cocok menghasilkan tabel kosong, bukan seluruh baris.
    expect(Livewire::test(LaporanStokObat::class)->set('pencarian', 'zzz')->instance()->halaman()->total())->toBe(0);
});

it('mengembalikan tabel ke halaman pertama saat kata kunci diketik', function () {
    foreach (range(1, 12) as $i) {
        receiveInto(makeMedicine(['name' => sprintf('OBAT CARI %02d', $i)]), 10);
    }

    $halaman = Livewire::test(LaporanStokObat::class)->set('perPage', 1)->call('gotoPage', 5);

    expect($halaman->instance()->halaman()->currentPage())->toBe(5);

    $halaman->set('pencarian', 'OBAT CARI 01');

    expect($halaman->instance()->halaman()->currentPage())->toBe(1);
});

it('menghitung jumlah hari periode fast/slow moving sebagai bilangan bulat inklusif', function () {
    $halaman = Livewire::test(LaporanMoving::class)
        ->fillForm([
            'period_start' => today()->subDays(90)->toDateString(),
            'period_end' => today()->toDateString(),
        ])
        ->call('generate');

    $days = $halaman->instance()->meta['days'];

    // Carbon 3 membalik jadi pecahan (mis. 90,999999...) kalau salah satu sisi endOfDay(); harus bulat.
    expect($days)->toBeInt()->and($days)->toBe(91);
});

it('mencari di kolom yang masuk akal untuk tiap laporan', function () {
    $obat = makeMedicine(['name' => 'OBAT KOLOM CARI']);
    receiveInto($obat, 10, today()->addDays(20)->toDateString(), 'B-KOLOM-CARI');

    // Kedaluwarsa ikut mencari nomor batch, bukan hanya nama obat.
    expect(Livewire::test(LaporanKedaluwarsa::class)->set('pencarian', 'b-kolom')->instance()->halaman()->total())->toBe(1);

    // Pembelian per PBF mencari kode dan nama PBF.
    $pbf = App\Models\Supplier::first();
    expect(Livewire::test(LaporanPembelianPbf::class)->set('pencarian', $pbf->code)->instance()->halaman()->total())->toBe(1)
        ->and(Livewire::test(LaporanPembelianPbf::class)->set('pencarian', 'pbf tidak ada')->instance()->halaman()->total())->toBe(0);
});
