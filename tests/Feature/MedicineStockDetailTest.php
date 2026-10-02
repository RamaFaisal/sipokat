<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Gate;

/**
 * Kartu stok ditampilkan kronologis, paling lama di atas (dibalik 2026-10-03 atas permintaan
 * peneliti: bentuk ini yang lazim dibaca sebagai kartu stok/buku besar, Stok Awal di atas dan
 * Stok Akhir di bawah, dan konsisten dengan urutan di Detail Stock Opname). StockCardService
 * sudah menghitung saldo berjalan menaik; halaman ini tidak lagi membaliknya untuk tampilan.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menampilkan kartu stok sungguhan dari yang paling lama di halaman', function () {
    $obat = makeMedicine(['min_stock' => 10]);
    $movement = app(StockMovementService::class);

    $ro = ReceiveOrder::create([
        'receive_order_number' => 'RO-URUT-0001',
        'supplier_id' => test()->supplier->id,
        'invoice_number' => 'INV-URUT-0001',
        'receive_date' => today()->toDateString(),
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
        'batch_number' => 'B1',
        'expired_date' => today()->addYear()->startOfMonth()->toDateString(),
    ]);
    $movement->recordReceipt($ro);

    $order1 = Order::create(['order_code' => 'ORD-URUT-0001', 'order_date' => today()->toDateString(), 'grand_total' => 0]);
    OrderItem::create(['order_id' => $order1->id, 'medicine_id' => $obat->id, 'medicine_name' => $obat->name, 'qty' => 30, 'price' => FIXTURE_SALE_PRICE]);
    $movement->recordSale($order1);

    $order2 = Order::create(['order_code' => 'ORD-URUT-0002', 'order_date' => today()->toDateString(), 'grand_total' => 0]);
    OrderItem::create(['order_id' => $order2->id, 'medicine_id' => $obat->id, 'medicine_name' => $obat->name, 'qty' => 20, 'price' => FIXTURE_SALE_PRICE]);
    $movement->recordSale($order2);

    // Semua bertanggal hari ini: kartu stok menyaring bulan berjalan, jadi memakai hari-hari
    // sebelumnya membuat tes ini merah setiap tanggal 1. Urutan dalam satu tanggal mengikuti id,
    // paling lama di atas: RO-URUT-0001 (saldo 100), ORD-URUT-0001 (70), ORD-URUT-0002 (50).
    $this->get('/admin/medicine-stock-detail?record='.$obat->id)
        ->assertOk()
        ->assertSeeInOrder(['RO-URUT-0001', 'ORD-URUT-0001', 'ORD-URUT-0002']);
});

it('membuat filter Tahun, Bulan, dan Supplier langsung berlaku tanpa tombol Filter', function () {
    $obat = makeMedicine();

    $html = $this->get('/admin/medicine-stock-detail?record='.$obat->id)
        ->assertOk()
        ->getContent();

    // Select-nya memakai wire:model.live, bukan lagi dibungkus <form wire:submit.prevent>, dan
    // tombol "Filter" sudah tidak ada sama sekali (hanya aksi Export yang tersisa di slot itu).
    // Satu-satunya "Filter" yang tersisa di halaman ini adalah nama properti Livewire
    // tableFilters/tableDeferredFilters bawaan InteractsWithTable, bukan teks tombol.
    expect($html)->toContain('wire:model.live="year"')
        ->toContain('wire:model.live="month"')
        ->toContain('wire:model.live="selectedSupplier"')
        ->not->toContain('wire:submit.prevent="applyFilters"')
        ->not->toContain('>Filter<');
});
