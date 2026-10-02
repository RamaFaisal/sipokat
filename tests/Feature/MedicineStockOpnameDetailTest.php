<?php

use App\Filament\Resources\MedicineStockOpnames\Pages\ViewMedicineStockOpname;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Halaman "Detail Stock Opname" tidak punya filter, jadi urutan tampil hanya diatur lewat
 * defaultSort(). Baris harus dari yang paling lama, contohnya jam 1.00 lalu jam 1.05 (revisi
 * September 2026), bukan dari yang paling baru.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menampilkan baris penyesuaian opname dari yang paling lama', function () {
    $obat = makeMedicine();

    $opname = MedicineStockOpname::create([
        'opname_number' => 'OPM-URUT-0001',
        'opname_date' => today()->toDateString(),
        'status' => 'in_stock',
    ]);

    $items = [];
    foreach (['PALING LAMA', 'TENGAH', 'PALING BARU'] as $note) {
        $items[$note] = MedicineStockOpnameItem::create([
            'medicine_stock_opname_id' => $opname->id,
            'medicine_id' => $obat->id,
            'qty' => 1,
            'type_account' => 'D',
            'hpp' => 0,
            'note' => $note,
        ]);
    }

    // Jam dipaksa supaya urutan tidak bergantung pada urutan insert (bisa sama persis di sqlite).
    $items['PALING LAMA']->forceFill(['created_at' => today()->setTime(1, 0, 0)])->save();
    $items['TENGAH']->forceFill(['created_at' => today()->setTime(1, 5, 0)])->save();
    $items['PALING BARU']->forceFill(['created_at' => today()->setTime(1, 10, 0)])->save();

    $page = Livewire::test(ViewMedicineStockOpname::class, ['record' => $opname->getRouteKey()]);

    $urutan = $page->instance()->getTableRecords()->pluck('note')->values()->all();

    expect($urutan)->toBe(['PALING LAMA', 'TENGAH', 'PALING BARU']);
});
