<?php

use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Filament\Widgets\LowStockMedicinesWidget;
use App\Filament\Widgets\SawTop10RestockWidget;
use App\Models\User;
use Database\Seeders\SawCriteriaSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Batas baris tiap widget dashboard: Stok 10, Kedaluwarsa 5, Prioritas Restock 5.
 *
 * Yang dijaga bukan sekadar jumlah barisnya, melainkan bahwa **pencarian menyaring seluruh data
 * dulu, baru dipotong**. Kalau urutannya terbalik, obat di luar sepuluh teratas tidak akan pernah
 * bisa ditemukan lewat kotak pencarian, dan batas baris berubah dari peringkas menjadi penghalang.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('membatasi widget stok pada sepuluh obat paling sedikit', function () {
    // Stok menaik: OBAT STOK 01 paling sedikit, OBAT STOK 12 paling banyak.
    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT STOK %02d', $i)]);
        receiveInto($obat, $i);
    }

    Livewire::test(LowStockMedicinesWidget::class)
        ->assertOk()
        ->assertSee('OBAT STOK 01')
        ->assertSee('OBAT STOK 10')
        ->assertDontSee('OBAT STOK 11')
        ->assertDontSee('OBAT STOK 12');
});

it('tetap menemukan obat di luar sepuluh teratas lewat pencarian', function () {
    foreach (range(1, 12) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT STOK %02d', $i)]);
        receiveInto($obat, $i);
    }

    Livewire::test(LowStockMedicinesWidget::class)
        ->set('tableSearch', 'OBAT STOK 12')
        ->assertOk()
        ->assertSee('OBAT STOK 12');
});

it('membatasi widget kedaluwarsa pada lima batch terdekat', function () {
    foreach (range(1, 6) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT ED %02d', $i)]);
        receiveInto($obat, 10, today()->addDays($i * 10)->toDateString(), sprintf('B-ED-%02d', $i));
    }

    Livewire::test(ExpiringMedicinesWidget::class)
        ->assertOk()
        ->assertSee('B-ED-01')
        ->assertSee('B-ED-05')
        ->assertDontSee('B-ED-06');
});

it('tetap menemukan batch di luar lima terdekat lewat pencarian', function () {
    foreach (range(1, 6) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT ED %02d', $i)]);
        receiveInto($obat, 10, today()->addDays($i * 10)->toDateString(), sprintf('B-ED-%02d', $i));
    }

    Livewire::test(ExpiringMedicinesWidget::class)
        ->set('tableSearch', 'OBAT ED 06')
        ->assertOk()
        ->assertSee('B-ED-06');
});

it('membatasi widget prioritas restock pada lima obat teratas', function () {
    $this->seed(SawCriteriaSeeder::class);

    // Rasio stok menaik: yang stoknya paling tipis menempati peringkat teratas.
    foreach (range(1, 7) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT SAW %02d', $i), 'min_stock' => 20]);
        receiveInto($obat, $i * 20);
        sellFrom($obat, $i);
    }

    $render = Livewire::test(SawTop10RestockWidget::class)->assertOk()->html();
    $tampil = collect(range(1, 7))
        ->filter(fn (int $i): bool => str_contains($render, sprintf('OBAT SAW %02d', $i)))
        ->count();

    expect($tampil)->toBe(5);
});

it('tetap menemukan obat di luar lima teratas peringkat lewat pencarian', function () {
    $this->seed(SawCriteriaSeeder::class);

    foreach (range(1, 7) as $i) {
        $obat = makeMedicine(['name' => sprintf('OBAT SAW %02d', $i), 'min_stock' => 20]);
        receiveInto($obat, $i * 20);
        sellFrom($obat, $i);
    }

    Livewire::test(SawTop10RestockWidget::class)
        ->set('tableSearch', 'OBAT SAW 07')
        ->assertOk()
        ->assertSee('OBAT SAW 07');
});
