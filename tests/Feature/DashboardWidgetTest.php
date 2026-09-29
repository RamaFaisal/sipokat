<?php

use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Filament\Widgets\LowStockMedicinesWidget;
use App\Filament\Widgets\SawTop10RestockWidget;
use App\Models\User;
use Database\Seeders\SawCriteriaSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Ketiga widget dashboard memuat **seluruh** datanya dan digulir di dalam kotaknya; angka 10 pada
 * kelas CSS hanya menentukan berapa baris yang terlihat sekaligus, bukan berapa baris yang dimuat.
 *
 * Yang dijaga di sini: tidak ada baris yang hilang dari widget, urutannya benar (paling mendesak di
 * atas), dan pencarian bekerja atas seluruh data. Tinggi kotaknya urusan CSS, bukan tes.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('memuat seluruh obat aktif di widget stok, bukan hanya yang terlihat', function () {
    // Stok menaik: OBAT STOK 01 paling sedikit, OBAT STOK 14 paling banyak.
    foreach (range(1, 14) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT STOK %02d', $i)]);
        receiveInto($obat, $i);
    }

    $render = Livewire::test(LowStockMedicinesWidget::class)->assertOk()->html();

    foreach (range(1, 14) as $i) {
        expect($render)->toContain(sprintf('OBAT STOK %02d', $i));
    }

    // Yang paling tipis harus berada di atas yang paling banyak.
    expect(strpos($render, 'OBAT STOK 01'))->toBeLessThan(strpos($render, 'OBAT STOK 14'));
});

it('menemukan obat lewat pencarian di widget stok', function () {
    foreach (range(1, 14) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT STOK %02d', $i)]);
        receiveInto($obat, $i);
    }

    Livewire::test(LowStockMedicinesWidget::class)
        ->set('tableSearch', 'OBAT STOK 14')
        ->assertOk()
        ->assertSee('OBAT STOK 14')
        ->assertDontSee('OBAT STOK 01');
});

it('menampilkan sepuluh batch paling dekat kedaluwarsa, termasuk yang di luar ambang', function () {
    // Jarak 40 hari: batch ke-3 dan seterusnya sudah lewat ambang 90 hari. Widget tetap
    // menampilkannya, karena pertanyaannya "mana yang paling dulu kedaluwarsa", bukan
    // "mana yang sudah masuk ambang".
    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT ED %02d', $i)]);
        receiveInto($obat, 10, today()->addDays($i * 40)->toDateString(), sprintf('B-ED-%02d', $i));
    }

    $render = Livewire::test(ExpiringMedicinesWidget::class)->assertOk()->html();

    foreach (range(1, 10) as $i) {
        expect($render)->toContain(sprintf('B-ED-%02d', $i));
    }

    expect($render)->not->toContain('B-ED-11')
        ->and($render)->not->toContain('B-ED-12')
        ->and(strpos($render, 'B-ED-01'))->toBeLessThan(strpos($render, 'B-ED-10'));
});

it('menemukan batch lewat pencarian di widget kedaluwarsa', function () {
    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT ED %02d', $i)]);
        receiveInto($obat, 10, today()->addDays($i * 40)->toDateString(), sprintf('B-ED-%02d', $i));
    }

    Livewire::test(ExpiringMedicinesWidget::class)
        ->set('tableSearch', 'OBAT ED 12')
        ->assertOk()
        ->assertSee('B-ED-12')
        ->assertDontSee('B-ED-01');
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

it('menemukan obat lewat pencarian di widget prioritas restock', function () {
    $this->seed(SawCriteriaSeeder::class);

    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT SAW %02d', $i), 'min_stock' => 20]);
        receiveInto($obat, $i * 20);
        sellFrom($obat, $i);
    }

    Livewire::test(SawTop10RestockWidget::class)
        ->set('tableSearch', 'OBAT SAW 12')
        ->assertOk()
        ->assertSee('OBAT SAW 12')
        ->assertDontSee('OBAT SAW 01');
});
