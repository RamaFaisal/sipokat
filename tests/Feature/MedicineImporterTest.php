<?php

use App\Filament\Imports\MedicineImporter;
use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\Unit;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Validation\ValidationException;

/**
 * Tombol "Import Obat" (Filament ImportAction, berkas CSV). Importer dijalankan
 * persis seperti job ImportCsv: __invoke(baris) dengan peta kolom header CSV.
 */
beforeEach(function () {
    seedMasterFixtures();
    Unit::create(['name' => 'Box', 'alias' => 'BOX']);
    MedicineCategories::create(['name' => 'Obat Keras', 'alias' => 'OBK']);
});

function runMedicineImporter(array $row): void
{
    $import = new Import;
    $import->user()->associate(\App\Models\User::factory()->create());
    $import->file_name = 'obat.csv';
    $import->file_path = 'obat.csv';
    $import->importer = MedicineImporter::class;
    $import->total_rows = 1;
    $import->save();

    $columnMap = [
        'name' => 'Nama Obat',
        'category_name' => 'Kategori',
        'unit_name' => 'Satuan Jual',
        'pack_unit_name' => 'Kemasan Pembelian',
        'pack_size' => 'Isi per Kemasan',
        'min_stock' => 'Stok Minimum',
        'status' => 'Status',
    ];

    (new MedicineImporter($import, $columnMap, []))($row);
}

it('mengimpor baris contoh dari template CSV', function () {
    runMedicineImporter([
        'Nama Obat' => 'ALLOPURINOL 100MG IFI',
        'Kategori' => 'Obat Keras',
        'Satuan Jual' => 'Strip',
        'Kemasan Pembelian' => 'Box',
        'Isi per Kemasan' => '10',
        'Stok Minimum' => '20',
        'Status' => 'active',
    ]);

    $m = Medicine::where('name', 'ALLOPURINOL 100MG IFI')->firstOrFail();

    expect($m->code)->toBe('OBT0001')
        ->and($m->category->name)->toBe('Obat Keras')
        ->and($m->unit->name)->toBe('Strip')
        ->and($m->packUnit->name)->toBe('Box')
        ->and($m->pack_size)->toBe(10)
        ->and($m->min_stock)->toBe(20)
        ->and($m->status)->toBe('active');
});

it('memakai min_stock bawaan bila kolomnya kosong dan melebur Obat Bebas Terbatas ke Obat Bebas', function () {
    runMedicineImporter([
        'Nama Obat' => 'paracetamol  500mg',
        'Kategori' => 'Obat Bebas Terbatas',
        'Satuan Jual' => 'Strip',
        'Kemasan Pembelian' => 'Box',
        'Isi per Kemasan' => '10',
        'Stok Minimum' => '',
        'Status' => '',
    ]);

    $m = Medicine::where('name', 'PARACETAMOL 500MG')->firstOrFail();

    expect($m->category->name)->toBe('Obat Bebas')
        ->and($m->min_stock)->toBe(Medicine::DEFAULT_MIN_STOCK_STRIP)
        ->and($m->status)->toBe('active');
});

it('menolak kategori yang tidak dikenal dengan pesan validasi', function () {
    runMedicineImporter([
        'Nama Obat' => 'X',
        'Kategori' => 'Narkotika',
        'Satuan Jual' => 'Strip',
        'Kemasan Pembelian' => 'Box',
        'Isi per Kemasan' => '10',
        'Stok Minimum' => '',
        'Status' => '',
    ]);
})->throws(ValidationException::class, 'Kategori "Narkotika" tidak ditemukan.');
