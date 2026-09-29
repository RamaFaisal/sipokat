<?php

use App\Filament\Pages\LaporanKedaluwarsa;
use App\Filament\Pages\MedicineStockDetail;
use App\Filament\Pages\SawCalculation;
use App\Filament\Resources\MedicineStocks\MedicineStockResource;
use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Filament\Widgets\LowStockMedicinesWidget;
use App\Filament\Widgets\RingkasanStatWidget;
use App\Filament\Widgets\SawTop10RestockWidget;
use App\Models\User;
use Database\Seeders\SawCriteriaSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Ketiga widget tabel dashboard menampilkan **enam baris paling mendesak**, tanpa kotak pencarian.
 * Selebihnya dicapai lewat menu terkait, yang dituju dengan mengeklik judul widget; tiap baris
 * menuju kartu stok obatnya.
 *
 * Yang dijaga di sini: batas enam baris, urutan yang benar (paling mendesak di atas), dan kedua
 * tautan itu benar-benar ada di keluaran.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

/** Jumlah obat bernomor 01 sampai $sampai yang muncul di keluaran widget. */
function jumlahTampil(string $kelas, string $pola, int $sampai): int
{
    $render = Livewire::test($kelas)->assertOk()->html();

    return collect(range(1, $sampai))
        ->filter(fn (int $i): bool => str_contains($render, sprintf($pola, $i)))
        ->count();
}

it('menampilkan enam obat berstok paling tipis, yang paling tipis di atas', function () {
    // Stok menaik: OBAT STOK 01 paling sedikit.
    foreach (range(1, 9) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT STOK %02d', $i)]);
        receiveInto($obat, $i);
    }

    $render = Livewire::test(LowStockMedicinesWidget::class)->assertOk()->html();

    expect(jumlahTampil(LowStockMedicinesWidget::class, 'OBAT STOK %02d', 9))->toBe(6)
        ->and($render)->toContain('OBAT STOK 01')
        ->and($render)->not->toContain('OBAT STOK 09')
        ->and(strpos($render, 'OBAT STOK 01'))->toBeLessThan(strpos($render, 'OBAT STOK 06'));
});

it('menampilkan enam obat dengan kedaluwarsa terdekat', function () {
    foreach (range(1, 9) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT ED %02d', $i)]);
        receiveInto($obat, 10, today()->addDays($i * 40)->toDateString(), sprintf('B-ED-%02d', $i));
    }

    $render = Livewire::test(ExpiringMedicinesWidget::class)->assertOk()->html();

    expect(jumlahTampil(ExpiringMedicinesWidget::class, 'OBAT ED %02d', 9))->toBe(6)
        ->and($render)->toContain('OBAT ED 01')
        ->and($render)->not->toContain('OBAT ED 09');
});

it('menampilkan enam obat teratas peringkat restock', function () {
    $this->seed(SawCriteriaSeeder::class);

    foreach (range(1, 9) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT SAW %02d', $i), 'min_stock' => 20]);
        receiveInto($obat, $i * 20);
        sellFrom($obat, $i);
    }

    expect(jumlahTampil(SawTop10RestockWidget::class, 'OBAT SAW %02d', 9))->toBe(6);
});

it('menjadikan judul widget tautan ke menu terkait', function () {
    $this->seed(SawCriteriaSeeder::class);
    $obat = makeMedicine(['min_stock' => 20]);
    receiveInto($obat, 50, today()->addDays(30)->toDateString());
    sellFrom($obat, 5);

    expect(Livewire::test(LowStockMedicinesWidget::class)->html())
        ->toContain(MedicineStockResource::getUrl())
        ->and(Livewire::test(ExpiringMedicinesWidget::class)->html())
        ->toContain(LaporanKedaluwarsa::getUrl())
        ->and(Livewire::test(SawTop10RestockWidget::class)->html())
        ->toContain(SawCalculation::getUrl());
});

it('menjadikan tiap baris tautan ke kartu stok obatnya', function () {
    $this->seed(SawCriteriaSeeder::class);
    $obat = makeMedicine(['min_stock' => 20]);
    receiveInto($obat, 50, today()->addDays(30)->toDateString());
    sellFrom($obat, 5);

    $tujuan = MedicineStockDetail::getUrl(['record' => $obat->id]);

    foreach ([LowStockMedicinesWidget::class, ExpiringMedicinesWidget::class, SawTop10RestockWidget::class] as $kelas) {
        expect(Livewire::test($kelas)->html())->toContain($tujuan);
    }
});

it('menyebutkan periode permintaan di widget prioritas restock', function () {
    $this->seed(SawCriteriaSeeder::class);
    $obat = makeMedicine(['min_stock' => 20]);
    receiveInto($obat, 100);
    sellFrom($obat, 10);

    // Waktu perhitungan tetap ditampilkan di halaman SPK; di widget cukup periodenya.
    Livewire::test(SawTop10RestockWidget::class)
        ->assertOk()
        ->assertSee('Periode permintaan');
});

it('tidak lagi menampilkan kotak pencarian di ketiga widget', function (string $kelas) {
    $obat = makeMedicine();
    receiveInto($obat, 10, today()->addDays(30)->toDateString());

    if ($kelas === SawTop10RestockWidget::class) {
        $this->seed(SawCriteriaSeeder::class);
    }

    expect(Livewire::test($kelas)->assertOk()->html())->not->toContain('fi-ta-search-field');
})->with([
    'stok' => LowStockMedicinesWidget::class,
    'kedaluwarsa' => ExpiringMedicinesWidget::class,
    'prioritas restock' => SawTop10RestockWidget::class,
]);

it('menyusun kartu statistik bertahap menurut lebar layar', function () {
    $render = Livewire::test(RingkasanStatWidget::class)->assertOk()->html();

    // Grid ditulis sendiri di blade, bukan lewat variabel CSS Filament: satu kolom di ponsel,
    // dua mulai md (768px), empat mulai xl (1280px).
    expect($render)->toContain('grid-cols-1')
        ->and($render)->toContain('md:grid-cols-2')
        ->and($render)->toContain('xl:grid-cols-4');
});
