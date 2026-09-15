<?php

use App\Filament\Imports\SupplierImporter;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Models\Supplier;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Kode PBF dibuat sistem dari inisial nama — tidak diketik di form maupun di berkas import.
 */
it('membentuk kode dari inisial nama tanpa kata badan usaha', function () {
    expect(Supplier::codeFromName('PT. Nisa Permata Mulia'))->toBe('NPM')
        ->and(Supplier::codeFromName('PT Kimia Farma'))->toBe('KF')
        ->and(Supplier::codeFromName('Enseval'))->toBe('ENS')
        ->and(Supplier::codeFromName('CV Anugrah Farma Sejahtera Abadi'))->toBe('AFS')
        ->and(Supplier::codeFromName('PT'))->toBe('PT')
        ->and(Supplier::codeFromName(''))->toBe('PBF');
});

it('memberi kode otomatis saat create dan menambah angka bila inisialnya sudah dipakai, termasuk yang dihapus', function () {
    $a = Supplier::create(['name' => 'PT Kimia Farma', 'status' => 'active']);
    $b = Supplier::create(['name' => 'PT Kalbe Farma', 'status' => 'active']);
    $b->delete();
    $c = Supplier::create(['name' => 'Kusuma Farma', 'status' => 'active']);
    $manual = Supplier::create(['code' => 'NPM', 'name' => 'Nisa', 'status' => 'active']);

    expect($a->code)->toBe('KF')
        ->and($b->code)->toBe('KF2')
        ->and($c->code)->toBe('KF3')
        ->and($manual->code)->toBe('NPM');
});

it('menampilkan pratinjau kode di form tambah dan mengunci kode di form edit', function () {
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'PT. Nisa Permata Mulia'])
        ->assertSchemaStateSet(['code' => 'NPM'])
        ->fillForm(['address' => 'Demak', 'status' => 'active'])
        ->call('create')
        ->assertHasNoFormErrors();

    $s = Supplier::where('name', 'PT. Nisa Permata Mulia')->firstOrFail();
    expect($s->code)->toBe('NPM');

    Livewire::test(EditSupplier::class, ['record' => $s->getRouteKey()])
        ->fillForm(['name' => 'PT. Nisa Permata Mulia Jaya', 'code' => 'XXX'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($s->fresh()->code)->toBe('NPM');
});

it('mengimpor PBF dari CSV tanpa kolom kode dan mengenali nama yang sudah ada', function () {
    Supplier::create(['name' => 'PT Kimia Farma', 'status' => 'active', 'phone' => '000']);

    $import = new Import;
    $import->user()->associate(User::factory()->create());
    $import->file_name = $import->file_path = 'pbf.csv';
    $import->importer = SupplierImporter::class;
    $import->total_rows = 2;
    $import->save();

    $columnMap = [
        'name' => 'Nama Supplier',
        'address' => 'Alamat',
        'phone' => 'Telepon',
        'fax' => 'Fax',
        'status' => 'Status',
    ];
    $importer = new SupplierImporter($import, $columnMap, []);

    $importer(['Nama Supplier' => 'pt kimia farma', 'Alamat' => 'Semarang', 'Telepon' => '(024) 1', 'Fax' => '(024) 2', 'Status' => 'active']);
    $importer(['Nama Supplier' => 'PT. Nisa Permata Mulia', 'Alamat' => 'Demak', 'Telepon' => '', 'Fax' => '', 'Status' => '']);

    expect(Supplier::count())->toBe(2);

    $kf = Supplier::where('code', 'KF')->firstOrFail();
    expect($kf->phone)->toBe('(024) 1')->and($kf->fax)->toBe('(024) 2');

    expect(Supplier::where('name', 'PT. Nisa Permata Mulia')->value('code'))->toBe('NPM');
});

it('PbfJatengSeeder memuat 8 PBF Semarang/Demak/Kudus dengan kode inisial dan idempoten', function () {
    $this->seed(\Database\Seeders\PbfJatengSeeder::class);
    $this->seed(\Database\Seeders\PbfJatengSeeder::class);

    expect(Supplier::count())->toBe(8)
        ->and(Supplier::where('name', 'PT. Nisa Permata Mulia')->value('code'))->toBe('NPM')
        ->and(Supplier::where('name', 'PT Enseval Putera Megatrading Tbk')->value('code'))->toBe('EPM')
        ->and(Supplier::where('name', 'PT Farmandika Al-Nur')->value('code'))->toBe('FAN')
        ->and(Supplier::where('name', 'PT Sehat Bersama Sejahtera')->value('fax'))->toBeNull()
        ->and(Supplier::where('name', 'PT Penta Valent')->value('fax'))->toBe('(0751) 34006');
});