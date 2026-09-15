<?php

use App\Filament\Pages\SawCalculation as SawCalculationPage;
use App\Filament\Resources\Medicines\Pages\CreateMedicine;
use App\Filament\Resources\MedicineStockOpnames\Pages\CreateMedicineStockOpname;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\ReceiveOrders\Pages\CreateReceiveOrder;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\ReceiveOrder;
use App\Models\SawCalculation;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockCardService;
use Database\Seeders\SawCriteriaSeeder;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/**
 * E7: alur lengkap lewat halaman Filament sungguhan (bukan service langsung):
 * obat baru → RO tanpa PO → jual → SAW → "Buat PO" dari ranking → RO per faktur dari PO
 * → jual FEFO lintas batch → opname → SAW ulang. Otorisasi Shield dilewati.
 */
/** Kunci baris pertama repeater (baris bawaan berkunci uuid, bukan angka). */
function firstRowKey(\Livewire\Features\SupportTesting\Testable $c, string $repeater): string|int
{
    return array_key_first($c->get("data.{$repeater}") ?? []) ?? 0;
}

/** Isi baris pertama repeater alih-alih menambah baris baru berkunci angka. */
function firstRow(\Livewire\Features\SupportTesting\Testable $c, string $repeater, array $row): array
{
    $key = firstRowKey($c, $repeater);
    $data = [];
    foreach ($row as $field => $value) {
        $data["{$repeater}.{$key}.{$field}"] = $value;
    }

    return $data;
}

function newOrder(Medicine $obat, int $qty, int $price): \Livewire\Features\SupportTesting\Testable
{
    $page = Livewire::test(CreateOrder::class);

    return $page
        ->fillForm(['order_date' => today()->toDateString()] + firstRow($page, 'items', ['medicine_id' => $obat->id, 'qty' => $qty, 'price' => $price]))
        ->call('create');
}

beforeEach(function () {
    seedMasterFixtures();
    $this->seed(SawCriteriaSeeder::class);
    $this->box = Unit::create(['name' => 'Box', 'alias' => 'BOX']);
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

it('menjalankan alur obat → RO → jual → SAW → PO dari ranking → RO dari PO → jual FEFO → opname → SAW', function () {
    $stockCard = app(StockCardService::class);
    $edNear = today()->addMonths(2)->startOfMonth();
    $edFar = today()->addMonths(18)->startOfMonth();

    // 1. Master obat: satuan jual Strip, kemasan beli Box isi 10, min_stock 20.
    Livewire::test(CreateMedicine::class)
        ->fillForm([
            'name' => 'obat alur 500mg',
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'pack_unit_id' => $this->box->id,
            'pack_size' => 10,
            'min_stock' => 20,
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $obat = Medicine::where('name', 'OBAT ALUR 500MG')->firstOrFail();
    expect($obat->code)->toMatch('/^OBT\d{4}$/');

    // Pembanding: obat kedua dengan stok melimpah supaya ranking bermakna.
    $lain = makeMedicine(['min_stock' => 10]);
    receiveInto($lain, 100, $edFar->toDateString());

    // 2. RO tanpa PO, faktur PBF: 2 Box @ Rp40.000 → 20 Strip @ Rp4.000, batch B1 (ED dekat).
    $ro1Page = Livewire::test(CreateReceiveOrder::class);
    $ro1Page
        ->fillForm([
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'F-001',
            'receive_date' => today()->toDateString(),
        ] + firstRow($ro1Page, 'items', [
            'medicine_id' => $obat->id,
            'pack_unit_id' => $this->box->id,
            'pack_size' => 10,
            'pack_qty' => 2,
            'pack_price' => 40000,
            'batch_number' => 'B1',
            'expired_month' => $edNear->format('m-Y'),
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    $ro1 = ReceiveOrder::where('invoice_number', 'F-001')->firstOrFail();
    expect($ro1->items->first()->qty)->toBe(20)
        ->and((int) $ro1->items->first()->price)->toBe(4000)
        ->and($stockCard->physicalStock($obat->id))->toBe(20)
        ->and($stockCard->currentHpp($obat->id))->toBe(4000);

    // Faktur yang sama dari PBF yang sama ditolak (R11).
    Livewire::test(CreateReceiveOrder::class)
        ->fillForm(['supplier_id' => $this->supplier->id, 'invoice_number' => 'F-001', 'receive_date' => today()->toDateString()])
        ->call('create')
        ->assertHasFormErrors(['invoice_number']);

    // 3. Jual 15 Strip: harga di bawah HPP ditolak, lalu berhasil dengan harga ≥ HPP.
    $rejected = newOrder($obat, 15, 3000);
    $rejected->assertHasFormErrors(['items.'.firstRowKey($rejected, 'items').'.price']);

    newOrder($obat, 15, 6000)->assertHasNoFormErrors();

    expect(Order::count())->toBe(1)
        ->and($stockCard->availableStock($obat->id))->toBe(5)
        ->and((int) Order::first()->grand_total)->toBe(90000);

    // 4. SAW: obat alur (rasio 5/20 = 0,25, permintaan 15, ED dekat) di tingkat 1 dari 2 alternatif.
    $saw = Livewire::test(SawCalculationPage::class)
        ->callAction('calculate')
        ->assertHasNoErrors();

    $calc = SawCalculation::latest('id')->firstOrFail();
    $result = $calc->results()->where('medicine_id', $obat->id)->firstOrFail();
    expect($calc->total_alternatives)->toBe(2)
        ->and($result->rank)->toBe(1)
        ->and($result->c1_stock)->toBe(5)
        ->and((float) $result->c1_raw)->toBe(0.25)
        ->and((int) $result->c4_raw)->toBe(4000);

    // 5. "Buat PO" dari ranking: jumlah bawaan ⌈20 ÷ 10⌉ = 2 Box, harga perkiraan = harga beli terakhir.
    $saw->callTableBulkAction('create_po', [$result->id], data: [
        'supplier_id' => $this->supplier->id,
        'po_date' => today()->toDateString(),
    ])->assertHasNoTableBulkActionErrors();

    $po = PurchaseOrder::firstOrFail();
    $poItem = $po->items()->firstOrFail();
    expect($po->status_receive_order)->toBe(PurchaseOrder::STATUS_PENDING)
        ->and($poItem->pack_qty)->toBe(2)
        ->and($poItem->qty)->toBe(20)
        ->and((int) $poItem->price)->toBe(4000);

    // 6. RO dari PO: centang item PO → baris terisi otomatis; lengkapi batch B2 (ED jauh) @ Rp45.000/Box.
    $ro2 = Livewire::test(CreateReceiveOrder::class)
        ->fillForm([
            'purchase_order_id' => $po->id,
            'invoice_number' => 'F-002',
            'receive_date' => today()->toDateString(),
            'po_pick' => [$obat->id],
        ]);
    $rows = $ro2->get('data.items');
    expect($rows)->toHaveCount(1);
    $key = array_key_first($rows);
    expect((int) $rows[$key]['pack_qty'])->toBe(2)->and((int) $rows[$key]['pack_size'])->toBe(10)->and($rows[$key]['subtotal'])->toBe('80.000');

    // Melebihi sisa PO ditolak (Q6).
    $ro2->fillForm(["items.{$key}.pack_qty" => 3, "items.{$key}.batch_number" => 'B2', "items.{$key}.expired_month" => $edFar->format('m-Y')])
        ->call('create')
        ->assertHasFormErrors(["items.{$key}.pack_qty"]);

    $ro2->fillForm(["items.{$key}.pack_qty" => 2, "items.{$key}.pack_price" => 45000])
        ->call('create')
        ->assertHasNoFormErrors();

    $po->refresh();
    expect($po->status_receive_order)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and($stockCard->physicalStock($obat->id))->toBe(25)
        // HPP: (5×4.000 + 20×4.500) ÷ 25 = 4.400
        ->and($stockCard->currentHpp($obat->id))->toBe(4400);

    // 7. Jual 22 → FEFO: 5 dari B1 (ED dekat) + 17 dari B2; permintaan qty > tersedia ditolak.
    $tooMany = newOrder($obat, 26, 6000);
    $tooMany->assertHasFormErrors(['items.'.firstRowKey($tooMany, 'items').'.qty']);

    newOrder($obat, 22, 6000)->assertHasNoFormErrors();

    $order2 = Order::latest('id')->firstOrFail();
    $consumptions = MedicineStock::where('order_id', $order2->id)->where('type_account', 'C')->get()->keyBy(fn ($c) => $c->layer->batch_number);
    expect($consumptions)->toHaveCount(2)
        ->and($consumptions['B1']->qty)->toBe(5)
        ->and($consumptions['B2']->qty)->toBe(17)
        ->and($stockCard->availableStock($obat->id))->toBe(3);

    // 8. Opname: B1 habis (tidak muncul), B2 sistem 3 → fisik 1 → penyesuaian C 2 pada lapisan B2.
    $opname = Livewire::test(CreateMedicineStockOpname::class);
    $opname->fillForm(['opname_date' => today()->toDateString()] + firstRow($opname, 'lines', ['medicine_id' => $obat->id]));
    $lines = $opname->get('data.lines');
    $lineKey = array_key_first($lines);
    $layers = $lines[$lineKey]['layers'];
    expect($layers)->toHaveCount(1);
    $lk = array_key_first($layers);
    expect($layers[$lk]['batch_label'])->toBe('B2')->and((int) $layers[$lk]['system_qty'])->toBe(3);

    $opname->fillForm(["lines.{$lineKey}.layers.{$lk}.physical_qty" => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($stockCard->availableStock($obat->id))->toBe(1)
        ->and($stockCard->physicalStock($obat->id))->toBe(1)
        ->and(MedicineStock::where('type_account', 'C')->whereNotNull('medicine_stock_opname_id')->value('qty'))->toBe(2);

    // 9. SAW ulang: PO sudah lengkap → tidak ada PO terbuka; obat alur tetap tingkat 1,
    //    C3 = ED batch B2 (terjauh yang bersisa), permintaan 37/30 hari.
    Livewire::test(SawCalculationPage::class)->callAction('calculate')->assertHasNoErrors();
    $calc2 = SawCalculation::latest('id')->firstOrFail();
    $r2 = $calc2->results()->where('medicine_id', $obat->id)->firstOrFail();
    expect($calc2->id)->toBeGreaterThan($calc->id)
        ->and($r2->rank)->toBe(1)
        ->and($r2->c1_stock)->toBe(1)
        ->and((int) $r2->c3_raw)->toBe((int) today()->diffInDays($edFar))
        ->and((int) $r2->c2_raw)->toBe(37)
        ->and((int) $r2->c4_raw)->toBe(4400);
    expect(PurchaseOrder::whereIn('status_receive_order', [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_PARTIAL])->count())->toBe(0);
});

it('membalik penjualan dan menolak hapus RO yang batch-nya sudah terpakai, lewat aksi tabel', function () {
    $stockCard = app(StockCardService::class);
    $obat = makeMedicine(['min_stock' => 10]);
    $ro = receiveInto($obat, 30, today()->addYear()->toDateString(), 'B1');

    newOrder($obat, 12, 8000)->assertHasNoFormErrors();
    $order = Order::firstOrFail();
    expect($stockCard->availableStock($obat->id))->toBe(18);

    // RO yang lapisannya sudah dikonsumsi tidak boleh dihapus (R8/B5) — RO tetap ada, stok tetap.
    Livewire::test(\App\Filament\Resources\ReceiveOrders\Pages\ListReceiveOrders::class)
        ->callTableAction('delete', $ro)
        ->assertNotified();
    expect(ReceiveOrder::whereKey($ro->id)->exists())->toBeTrue()
        ->and($stockCard->availableStock($obat->id))->toBe(18);

    // Hapus penjualan → baris C hilang sungguhan, stok kembali ke batch asal.
    Livewire::test(\App\Filament\Resources\Orders\Pages\ListOrders::class)
        ->callTableAction('delete', $order)
        ->assertHasNoTableActionErrors();
    expect(Order::whereKey($order->id)->exists())->toBeFalse()
        ->and(MedicineStock::where('type_account', 'C')->count())->toBe(0)
        ->and($stockCard->availableStock($obat->id))->toBe(30);

    // Sekarang RO boleh dihapus → lapisan D ikut hilang, stok 0.
    Livewire::test(\App\Filament\Resources\ReceiveOrders\Pages\ListReceiveOrders::class)
        ->callTableAction('delete', $ro)
        ->assertHasNoTableActionErrors();
    expect(ReceiveOrder::whereKey($ro->id)->exists())->toBeFalse()
        ->and(MedicineStock::count())->toBe(0)
        ->and($stockCard->physicalStock($obat->id))->toBe(0);
});

it('merender kartu stok, laporan rekap/moving, dan cetak RO dengan data berlapis', function () {
    $obat = makeMedicine(['min_stock' => 10]);
    $ro = receiveInto($obat, 30, today()->addYear()->toDateString(), 'B1');
    receiveInto($obat, 10, today()->addMonths(3)->toDateString(), 'B2');
    newOrder($obat, 12, 8000)->assertHasNoFormErrors();

    $this->get('/admin/medicine-stocks')->assertOk();
    $this->get('/admin/medicine-stock-detail?record='.$obat->id)
        ->assertOk()
        ->assertSee('B1')->assertSee('B2')->assertSee('HPP');

    Livewire::test(\App\Filament\Pages\LaporanRekap::class)
        ->fillForm(['period_start' => today()->subDays(29)->toDateString(), 'period_end' => today()->toDateString(), 'tipe' => 'penjualan'])
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee($obat->name);

    Livewire::test(\App\Filament\Pages\LaporanMoving::class)
        ->fillForm(['period_start' => today()->subDays(29)->toDateString(), 'period_end' => today()->toDateString()])
        ->call('generate')
        ->assertHasNoErrors()
        ->assertSee($obat->name);

    // Cetak RO: PDF terender (modal Filament tidak bisa diuji headless, jadi panggil pembuat PDF-nya langsung).
    $pdf = (new \App\Filament\Resources\ReceiveOrders\Tables\ReceiveOrdersTable)->previewProgressReportPdf($ro);
    expect($pdf->getContent())->toStartWith('%PDF');
});
