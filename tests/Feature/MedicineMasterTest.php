<?php

use App\Models\Medicine;
use App\Models\Unit;

/**
 * Karakterisasi master obat versi ramping (rencana-revisi-2026-09 Bagian 1):
 * kode sekuensial, bawaan min_stock per satuan, normalisasi nama,
 * dan harga perkiraan PO dari RO terakhir.
 */
beforeEach(function () {
    seedMasterFixtures();
});

it('membuat kode OBT#### berurutan dan tidak memakai ulang nomor obat yang dihapus', function () {
    $a = makeMedicine();
    $b = makeMedicine();

    expect($a->code)->toBe('OBT0001')
        ->and($b->code)->toBe('OBT0002');

    $b->delete(); // soft delete

    expect(makeMedicine()->code)->toBe('OBT0003');
});

it('tidak menghitung ulang kode saat obat diubah', function () {
    $m = makeMedicine();
    $m->update(['name' => 'NAMA BARU 10MG']);

    expect($m->fresh()->code)->toBe('OBT0001');
});

it('mengisi min_stock bawaan 20 untuk satuan jual Strip', function () {
    $m = makeMedicine(['min_stock' => 0]);

    expect($m->min_stock)->toBe(20);
});

it('mengisi min_stock bawaan = isi kemasan untuk satuan jual selain Strip', function () {
    $flask = Unit::create(['name' => 'Botol', 'alias' => 'FLS']);
    $box = Unit::create(['name' => 'Box', 'alias' => 'BOX']);

    $m = makeMedicine([
        'unit_id' => $flask->id,
        'pack_unit_id' => $box->id,
        'pack_size' => 6,
        'min_stock' => 0,
    ]);

    expect($m->min_stock)->toBe(6);
});

it('mempertahankan min_stock yang diisi sendiri', function () {
    expect(makeMedicine(['min_stock' => 35])->min_stock)->toBe(35);
});

it('menormalkan nama: uppercase dan spasi ganda dirapikan', function () {
    $m = makeMedicine(['name' => '  paracetamol   500 mg  ']);

    expect($m->name)->toBe('PARACETAMOL 500 MG');
});

it('mengembalikan harga item RO terakhir sebagai harga perkiraan PO', function () {
    $m = makeMedicine();

    expect($m->latestPurchasePrice())->toBeNull();

    receiveInto($m, 10);

    expect($m->latestPurchasePrice())->toBe((float) FIXTURE_PURCHASE_PRICE);
});

it('mengabaikan RO yang sudah dihapus saat mencari harga terakhir', function () {
    $m = makeMedicine();
    $ro = receiveInto($m, 10);
    $ro->delete();

    expect($m->latestPurchasePrice())->toBeNull();
});

it('menampilkan pratinjau kode di form tambah, menetapkannya saat simpan, dan tidak bisa diubah dari form edit', function () {
    \Illuminate\Support\Facades\Gate::before(fn () => true);
    $this->actingAs(\App\Models\User::factory()->create());
    makeMedicine(); // OBT0001 sudah terpakai

    $create = \Livewire\Livewire::test(\App\Filament\Resources\Medicines\Pages\CreateMedicine::class)
        ->assertSchemaStateSet(['code' => 'OBT0002']);

    $create->fillForm([
        'name' => 'obat baru 10mg',
        'category_id' => $this->category->id,
        'unit_id' => $this->unit->id,
        'pack_unit_id' => $this->unit->id,
        'pack_size' => 1,
        'min_stock' => 20,
        'status' => 'active',
    ])->call('create')->assertHasNoFormErrors();

    $m = Medicine::where('name', 'OBAT BARU 10MG')->firstOrFail();
    expect($m->code)->toBe('OBT0002');

    \Livewire\Livewire::test(\App\Filament\Resources\Medicines\Pages\EditMedicine::class, ['record' => $m->getRouteKey()])
        ->assertSchemaStateSet(['code' => 'OBT0002'])
        ->fillForm(['code' => 'OBT9999', 'name' => 'OBAT BARU 10MG'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($m->fresh()->code)->toBe('OBT0002');
});
