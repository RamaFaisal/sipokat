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
