<?php

use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Filament\Widgets\LowStockMedicinesWidget;
use App\Filament\Widgets\SawTop10RestockWidget;
use App\Models\User;
use Database\Seeders\SawCriteriaSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Ketiga widget dashboard memuat **seluruh** datanya dan digulir di dalam kartunya. Tidak ada
 * kotak pencarian dan tidak ada pemotongan jumlah baris, sehingga tidak ada data yang hanya bisa
 * dicapai lewat kata kunci; tinggi kartu dikunci lewat CSS, bukan lewat jumlah baris.
 *
 * Yang dijaga di sini: tidak ada baris yang hilang, dan urutannya benar (paling mendesak di atas).
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('memuat seluruh obat aktif di widget stok, paling tipis di atas', function () {
    // Stok menaik: OBAT STOK 01 paling sedikit, OBAT STOK 14 paling banyak.
    foreach (range(1, 14) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT STOK %02d', $i)]);
        receiveInto($obat, $i);
    }

    $render = Livewire::test(LowStockMedicinesWidget::class)->assertOk()->html();

    foreach (range(1, 14) as $i) {
        expect($render)->toContain(sprintf('OBAT STOK %02d', $i));
    }

    expect(strpos($render, 'OBAT STOK 01'))->toBeLessThan(strpos($render, 'OBAT STOK 14'));
});

it('memuat seluruh obat yang punya batch bersisa, terdekat di atas', function () {
    // Jarak 40 hari: obat ke-3 dan seterusnya sudah lewat ambang 90 hari, tetapi tetap dimuat.
    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT ED %02d', $i)]);
        receiveInto($obat, 10, today()->addDays($i * 40)->toDateString(), sprintf('B-ED-%02d', $i));
    }

    $render = Livewire::test(ExpiringMedicinesWidget::class)->assertOk()->html();

    foreach (range(1, 12) as $i) {
        expect($render)->toContain(sprintf('OBAT ED %02d', $i));
    }

    expect(strpos($render, 'OBAT ED 01'))->toBeLessThan(strpos($render, 'OBAT ED 12'));
});

it('memuat seluruh peringkat di widget prioritas restock', function () {
    $this->seed(SawCriteriaSeeder::class);

    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT SAW %02d', $i), 'min_stock' => 20]);
        receiveInto($obat, $i * 20);
        sellFrom($obat, $i);
    }

    $render = Livewire::test(SawTop10RestockWidget::class)->assertOk()->html();

    foreach (range(1, 12) as $i) {
        expect($render)->toContain(sprintf('OBAT SAW %02d', $i));
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

    $render = Livewire::test($kelas)->assertOk()->html();

    expect($render)->not->toContain('fi-ta-search-field');
})->with([
    'stok' => LowStockMedicinesWidget::class,
    'kedaluwarsa' => ExpiringMedicinesWidget::class,
    'prioritas restock' => SawTop10RestockWidget::class,
]);

it('menyusun kartu statistik bertahap menurut lebar layar', function () {
    $render = Livewire::test(App\Filament\Widgets\RingkasanStatWidget::class)->assertOk()->html();

    // Grid ditulis sendiri di blade, bukan lewat variabel CSS Filament: satu kolom di ponsel,
    // dua mulai md (768px), empat mulai xl (1280px).
    expect($render)->toContain('grid-cols-1')
        ->and($render)->toContain('md:grid-cols-2')
        ->and($render)->toContain('xl:grid-cols-4');
});
