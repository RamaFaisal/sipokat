<?php

use App\Filament\Resources\Medicines\Pages\ListMedicines;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Units\Pages\ListUnits;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Tiga tabel master dibuka dengan data terbaru di atas (permintaan peneliti 2026-09-30).
 *
 * Sebelumnya tidak ada `defaultSort` sama sekali, sehingga urutannya mengikuti urutan baris di
 * database, yaitu yang paling lama dimasukkan justru paling atas. Pada master riil 127 obat, obat
 * yang baru ditambahkan tenggelam di halaman terakhir.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menampilkan obat terbaru di baris teratas', function () {
    $lama = makeMedicine(['name' => 'OBAT LAMA URUT']);
    $lama->forceFill(['created_at' => now()->subMonth()])->save();

    $baru = makeMedicine(['name' => 'OBAT BARU URUT']);

    Livewire::test(ListMedicines::class)->assertCanSeeTableRecords([$baru, $lama], inOrder: true);
});

it('menampilkan PBF terbaru di baris teratas', function () {
    $lama = Supplier::create(['name' => 'PT. PBF LAMA URUT']);
    $lama->forceFill(['created_at' => now()->subMonth()])->save();

    $baru = Supplier::create(['name' => 'PT. PBF BARU URUT']);

    Livewire::test(ListSuppliers::class)->assertCanSeeTableRecords([$baru, $lama], inOrder: true);
});

it('menampilkan satuan terbaru di baris teratas', function () {
    $lama = Unit::create(['name' => 'SATUAN LAMA URUT', 'alias' => 'SLU']);
    $lama->forceFill(['created_at' => now()->subMonth()])->save();

    $baru = Unit::create(['name' => 'SATUAN BARU URUT', 'alias' => 'SBU']);

    Livewire::test(ListUnits::class)->assertCanSeeTableRecords([$baru, $lama], inOrder: true);
});
