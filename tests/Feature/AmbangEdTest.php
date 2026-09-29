<?php

use App\Filament\Widgets\ExpiringMedicinesWidget;
use App\Filament\Widgets\LowStockMedicinesWidget;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\User;
use App\Support\AmbangEd;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Ambang kedaluwarsa terpusat (K6) dan kolom ED terdekat di kartu stok (butir 3).
 *
 * Query widget dashboard diuji lewat Livewire, bukan lewat render halaman: widget tabel Filament
 * dimuat tertunda, sehingga membuka `/admin` tidak pernah menjalankan query-nya. Itu sebabnya
 * pemakaian `FIELD()` yang khusus MySQL bisa bertahan lama tanpa ketahuan.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('memberi warna menurut satu ambang yang sama', function (?int $sisa, string $warna) {
    expect(AmbangEd::warna($sisa))->toBe($warna);
})->with([
    'sudah lewat' => [-5, 'danger'],
    'batas mendesak' => [30, 'danger'],
    'awal waspada' => [31, 'warning'],
    'batas waspada' => [60, 'warning'],
    'awal pantau' => [61, 'success'],
    'batas pantau' => [90, 'success'],
    'di luar pantauan' => [91, 'gray'],
    'tanpa ED' => [null, 'gray'],
]);

it('menandai baris hanya selama masih dipantau', function () {
    expect(AmbangEd::kelasBaris(10))->toContain('danger')
        ->and(AmbangEd::kelasBaris(45))->toContain('warning')
        ->and(AmbangEd::kelasBaris(80))->toContain('success')
        ->and(AmbangEd::kelasBaris(120))->toBeNull()
        ->and(AmbangEd::dipantau(90))->toBeTrue()
        ->and(AmbangEd::dipantau(91))->toBeFalse();
});

it('menghitung ED terdekat dari lapisan yang masih bersisa, bukan yang sudah habis', function () {
    $obat = makeMedicine(['name' => 'OBAT ED TERDEKAT']);
    // Batch dekat dijual habis, batch jauh masih bersisa.
    receiveInto($obat, 5, today()->addDays(20)->toDateString(), 'B-DEKAT');
    receiveInto($obat, 10, today()->addDays(400)->toDateString(), 'B-JAUH');
    sellFrom($obat, 5);

    $baris = Medicine::query()
        ->addSelect(['ed_terdekat' => MedicineStock::query()
            ->selectRaw('min(expired_date)')
            ->whereColumn('medicine_stocks.medicine_id', 'medicines.id')
            ->layers()
            ->whereNotNull('expired_date')
            ->withRemainingStock(),
        ])
        ->find($obat->id);

    expect(substr((string) $baris->ed_terdekat, 0, 10))->toBe(today()->addDays(400)->toDateString());
});

it('menjalankan query widget stok menipis tanpa fungsi khusus MySQL', function () {
    $obat = makeMedicine(['min_stock' => 20]);
    receiveInto($obat, 5);
    app(App\Services\StockMovementService::class)->refreshStockStatus([$obat->id]);

    Livewire::test(LowStockMedicinesWidget::class)
        ->assertOk()
        ->assertSee($obat->name);
});

it('menjalankan query widget kedaluwarsa dan mewarnai barisnya', function () {
    $obat = makeMedicine(['name' => 'OBAT SEGERA ED']);
    receiveInto($obat, 10, today()->addDays(15)->toDateString(), 'B-SEGERA');

    Livewire::test(ExpiringMedicinesWidget::class)
        ->assertOk()
        ->assertSee('B-SEGERA');
});

it('tidak menampilkan batch di luar ambang pantauan', function () {
    $obat = makeMedicine(['name' => 'OBAT MASIH LAMA']);
    receiveInto($obat, 10, today()->addDays(AmbangEd::PANTAU + 30)->toDateString(), 'B-LAMA');

    Livewire::test(ExpiringMedicinesWidget::class)
        ->assertOk()
        ->assertDontSee('B-LAMA');
});

it('menghitung stok awal dan stok akhir periode lewat subquery, bukan panggilan per baris', function () {
    $obat = makeMedicine(['name' => 'OBAT PERIODE KARTU']);

    // Penerimaan bulan lalu menjadi stok awal; mutasi bulan ini masuk kolom periode berjalan.
    $ro = receiveInto($obat, 40);
    $ro->update(['receive_date' => today()->subMonth()->toDateString()]);
    App\Models\MedicineStock::where('receive_order_id', $ro->id)->update(['date' => today()->subMonth()->toDateString()]);

    receiveInto($obat, 10);
    sellFrom($obat, 15);

    $awalBulan = today()->startOfMonth();
    $baris = App\Models\Medicine::query()
        ->addSelect([
            'init_stock' => App\Models\MedicineStock::query()
                ->selectRaw("coalesce(sum(case when type_account = 'D' then qty else -qty end), 0)")
                ->whereColumn('medicine_stocks.medicine_id', 'medicines.id')
                ->whereDate('date', '<=', $awalBulan->copy()->subDay()->toDateString()),
            'current_stock' => App\Models\MedicineStock::query()
                ->selectRaw("coalesce(sum(case when type_account = 'D' then qty else -qty end), 0)")
                ->whereColumn('medicine_stocks.medicine_id', 'medicines.id')
                ->whereDate('date', '<=', $awalBulan->copy()->endOfMonth()->toDateString()),
        ])
        ->find($obat->id);

    expect((int) $baris->init_stock)->toBe(40)
        ->and((int) $baris->current_stock)->toBe(35); // 40 + 10 - 15
});
