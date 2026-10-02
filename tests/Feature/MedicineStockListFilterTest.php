<?php

use App\Filament\Resources\MedicineStocks\Pages\ListMedicineStocks;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * Filter periode (Tahun, Bulan) di menu Kartu Stok Obat harus langsung berlaku begitu Select
 * berubah, tanpa menekan tombol "Apply filters" (bug ditemukan revisi September 2026: tabel
 * memakai filter tunda bawaan Filament meski Select-nya sudah ->live()).
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menerapkan filter periode tanpa tombol dan menyembunyikan tombol terapkan', function () {
    $obat = makeMedicine(['min_stock' => 10]);
    $movement = app(StockMovementService::class);

    // Penerimaan di tengah bulan dua bulan lalu: di bulan berjalan stok awalnya sudah 100
    // (penerimaan sudah lewat), tapi di bulan penerimaan itu sendiri stok awalnya masih 0.
    $tanggalTerima = today()->subMonths(2)->startOfMonth()->addDays(5);

    $ro = ReceiveOrder::create([
        'receive_order_number' => 'RO-FILTER-0001',
        'supplier_id' => test()->supplier->id,
        'invoice_number' => 'INV-FILTER-0001',
        'receive_date' => $tanggalTerima->toDateString(),
    ]);
    ReceiveOrderItem::create([
        'receive_order_id' => $ro->id,
        'medicine_id' => $obat->id,
        'medicine_name' => $obat->name,
        'pack_unit_id' => $obat->unit_id,
        'pack_size' => 1,
        'pack_qty' => 100,
        'qty' => 100,
        'price' => FIXTURE_PURCHASE_PRICE,
        'batch_number' => 'B-FILTER',
        'expired_date' => today()->addYear()->startOfMonth()->toDateString(),
    ]);
    $movement->recordReceipt($ro);

    $page = Livewire::test(ListMedicineStocks::class);
    $baris = fn () => $page->instance()->getTableRecords()->firstWhere('id', $obat->id);

    // Bawaan: periode bulan berjalan, penerimaan sudah lewat, jadi stok awal = 100.
    expect((int) $baris()->init_stock)->toBe(100);

    // Pindah ke bulan penerimaan itu sendiri. Dibaca lewat tableFilters (bukan tableDeferredFilters)
    // karena itu, bukan jumlah baris yang berubah, yang menandakan filter benar-benar langsung:
    // Filament menulis ke tableDeferredFilters selama hasDeferredFilters() masih true, dan baru
    // menyalinnya ke tableFilters saat tombol "Apply filters" diklik (lihat getTableFiltersForm()
    // di vendor/filament/tables/src/Concerns/HasFilters.php). ->set('tableFilters...') di sini
    // meniru hasil akhirnya, bukan jalur pengisiannya, jadi baris di bawah ini TIDAK membuktikan
    // livenessnya sendirian, hanya membuktikan periode() membaca tableFilters dengan benar.
    $page->set('tableFilters.period.year', $tanggalTerima->year)
        ->set('tableFilters.period.month', $tanggalTerima->month);

    expect((int) $baris()->init_stock)->toBe(0)
        ->and((int) $baris()->current_stock)->toBe(100);

    // Bukti sesungguhnya bahwa Select menulis langsung ke tableFilters: deferFilters(false)
    // membuat getTableFiltersForm() memberi statePath 'tableFilters' + live() pada form filter,
    // bukan 'tableDeferredFilters' + partiallyRender(). Ini sumber kebenaran tunggal yang dipakai
    // Filament sendiri untuk menentukan apakah tombol "Apply filters" dirender (lihat
    // getFiltersApplyAction()->visible($this->hasDeferredFilters())).
    //
    // assertDontSee('Apply filters') TIDAK dipakai di sini: teks itu terpecah oleh baris baru di
    // markup Blade-nya Filament (mis. "Apply\n    filters"), sehingga pencarian substring persis
    // selalu gagal menemukannya baik tombolnya ada maupun tidak -- dibuktikan lewat deferFilters(false)
    // yang sengaja dihapus sementara saat menyiapkan tes ini, assertDontSee tetap lolos.
    expect($page->instance()->getTable()->hasDeferredFilters())->toBeFalse();
});
