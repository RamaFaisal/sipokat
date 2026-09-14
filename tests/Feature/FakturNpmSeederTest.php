<?php

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\ReceiveOrder;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\FakturNpmSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Support\Carbon;

/**
 * 14 faktur PT. Nisa Permata Mulia (Mei 2024) sebagai RO bergeser ke bulan berjalan.
 * Master obat dibuat minimal: kemasan beli = kemasan di faktur, isi 1 (isi sebenarnya diuji
 * di lingkungan nyata lewat master-data-obat.xlsx).
 */
beforeEach(function () {
    User::factory()->create();
    $this->seed(MasterDataSeeder::class);
    $category = MedicineCategories::first();
    $units = [];
    foreach (Unit::all() as $u) {
        $units[strtolower($u->name)] = $u;
        $units[strtolower($u->alias)] = $u;
    }
    $tablet = $units['tablet'];
    foreach (FakturNpmSeeder::INVOICES as [, , , $lines]) {
        foreach ($lines as [$name, $packAlias]) {
            $pack = $units[strtolower($packAlias)];
            Medicine::firstOrCreate(['name' => Medicine::normalizeName($name)], [
                'category_id' => $category->id,
                'unit_id' => $tablet->id,
                'pack_unit_id' => $pack->id,
                'pack_size' => 1,
                'min_stock' => 10,
            ]);
        }
    }
});

it('menulis 14 faktur dengan total sama seperti cetakan, tanggal & ED digeser, dan idempoten', function () {
    $this->seed(FakturNpmSeeder::class);

    $offset = (int) Carbon::parse('2024-05-01')->diffInMonths(today()->startOfMonth());
    expect(ReceiveOrder::count())->toBe(14);

    foreach (FakturNpmSeeder::INVOICES as [$no, $originDate, $total, $lines]) {
        $ro = ReceiveOrder::where('invoice_number', $no)->firstOrFail();
        expect((int) round($ro->total()))->toBe($total, "total {$no}");

        $expectedDate = Carbon::createFromFormat('d-m-Y', $originDate)->startOfDay()->addMonths($offset)->min(today());
        expect($ro->receive_date->toDateString())->toBe($expectedDate->toDateString());

        foreach ($lines as $i => [$name, , , , $batch, $ed]) {
            $item = $ro->items->firstWhere('batch_number', $batch);
            $expectedEd = $ed
                ? Carbon::create(2000 + (int) substr($ed, 3), (int) substr($ed, 0, 2), 1)->addMonths($offset)
                : $expectedDate->copy()->addMonths(FakturNpmSeeder::BLANK_ED_MONTHS)->startOfMonth();
            expect($item->expired_date->toDateString())->toBe($expectedEd->toDateString(), "ED {$name}");
        }
    }

    // Semua lapisan lewat service: satu baris D per baris faktur, ED terisi.
    expect(\App\Models\MedicineStock::layers()->count())->toBe(collect(FakturNpmSeeder::INVOICES)->sum(fn ($f) => count($f[3])))
        ->and(\App\Models\MedicineStock::layers()->whereNull('expired_date')->count())->toBe(0);

    $this->seed(FakturNpmSeeder::class);
    expect(ReceiveOrder::count())->toBe(14);
});
