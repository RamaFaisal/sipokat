<?php

use App\Models\Unit;

/**
 * Karakterisasi master obat versi ramping (rencana-revisi-2026-09 Bagian 1):
 * kode sekuensial, bawaan min_stock per satuan, normalisasi nama,
 * dan harga perkiraan PO dari RO terakhir.
 */
beforeEach(function () {
    seedMasterFixtures();
});

it('membuat kode OBT-#### berurutan dan tidak memakai ulang nomor obat yang dihapus', function () {
    $a = makeMedicine();
    $b = makeMedicine();

    expect($a->code)->toBe('OBT-0001')
        ->and($b->code)->toBe('OBT-0002');

    $b->delete(); // soft delete

    expect(makeMedicine()->code)->toBe('OBT-0003');
});

it('tidak menghitung ulang kode saat obat diubah', function () {
    $m = makeMedicine();
    $m->update(['name' => 'NAMA BARU 10MG']);

    expect($m->fresh()->code)->toBe('OBT-0001');
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
