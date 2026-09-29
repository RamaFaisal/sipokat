<?php

use App\Filament\Resources\MedicineStockOpnames\Schemas\MedicineStockOpnameForm;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Models\MedicineStockOpname;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\ReceiveOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu format nomor untuk PO, RO, penjualan, dan opname (K12, K13):
 * PREFIKS + YYYYMMDD + urutan empat digit, tanpa pemisah.
 */
beforeEach(function () {
    seedMasterFixtures();
});

it('membuat nomor berformat prefiks + tanggal + urutan empat digit', function (string $nomor, string $prefix) {
    expect($nomor)->toMatch('/^'.$prefix.'\d{8}\d{4}$/')
        ->and($nomor)->toBe($prefix.today()->format('Ymd').'0001');
})->with([
    'PO' => fn () => [PurchaseOrderForm::generatePONumber(), 'PO'],
    'RO' => fn () => [ReceiveOrder::nextNumber(), 'RO'],
    'penjualan' => fn () => [OrderForm::generateOrderCode(), 'ORD'],
    'opname' => fn () => [MedicineStockOpnameForm::nextNumber(), 'OPM'],
]);

it('tidak memakai dash sama sekali', function () {
    expect(PurchaseOrderForm::generatePONumber())->not->toContain('-')
        ->and(ReceiveOrder::nextNumber())->not->toContain('-')
        ->and(OrderForm::generateOrderCode())->not->toContain('-')
        ->and(MedicineStockOpnameForm::nextNumber())->not->toContain('-');
});

it('menaikkan urutan untuk dokumen berikutnya di hari yang sama', function () {
    $pertama = ReceiveOrder::nextNumber();
    makeReceiveOrderWithNumber($pertama);

    expect(ReceiveOrder::nextNumber())->toBe(substr($pertama, 0, -4).'0002');
});

it('memakai tanggal yang diberikan, bukan hari ini', function () {
    $nomor = ReceiveOrder::nextNumber(Carbon::parse('2024-05-17'));

    expect($nomor)->toBe('RO202405170001');
});

it('memisahkan urutan antar hari', function () {
    makeReceiveOrderWithNumber(ReceiveOrder::nextNumber(Carbon::parse('2024-05-17')));

    expect(ReceiveOrder::nextNumber(Carbon::parse('2024-05-18')))->toBe('RO202405180001')
        ->and(ReceiveOrder::nextNumber(Carbon::parse('2024-05-17')))->toBe('RO202405170002');
});

it('ikut menghitung dokumen yang sudah dihapus supaya nomor tidak terpakai dua kali', function () {
    $ro = makeReceiveOrderWithNumber(ReceiveOrder::nextNumber());
    $nomorTerhapus = $ro->receive_order_number;
    $ro->delete();

    expect(ReceiveOrder::nextNumber())->not->toBe($nomorTerhapus)
        ->and(ReceiveOrder::nextNumber())->toBe(substr($nomorTerhapus, 0, -4).'0002');
});

it('tidak terganggu nomor data demo yang berpola lain', function () {
    makeReceiveOrderWithNumber('RO-SPK-0007');

    expect(ReceiveOrder::nextNumber())->toBe('RO'.today()->format('Ymd').'0001');
});

/*
|--------------------------------------------------------------------------
| Migrasi penulisan ulang nomor lama
|--------------------------------------------------------------------------
|
| Suite tes selalu bermigrasi dari nol sehingga tidak pernah punya baris berformat lama.
| Barisnya dibuat manual di sini, lalu migrasinya dijalankan langsung.
|
*/

function jalankanMigrasiNomor(): void
{
    $migration = require database_path('migrations/2026_09_28_000001_unify_document_number_format.php');
    $migration->up();
}

it('menulis ulang nomor lama PO, RO, dan penjualan', function () {
    $po = makePurchaseOrderWithNumber('PO20260916-0001');
    $ro = makeReceiveOrderWithNumber('RO20260916-0012');
    $order = Order::create(['order_code' => 'ORD-202609270003', 'order_date' => today()->toDateString(), 'grand_total' => 0]);

    jalankanMigrasiNomor();

    expect($po->fresh()->po_number)->toBe('PO202609160001')
        ->and($ro->fresh()->receive_order_number)->toBe('RO202609160012')
        ->and($order->fresh()->order_code)->toBe('ORD202609270003');
});

it('menulis ulang nomor opname memakai tanggal opname dan menomori ulang per hari', function () {
    $a = makeOpnameWithNumber('OPM0001', '2026-09-16');
    $b = makeOpnameWithNumber('OPM0002', '2026-09-16');
    $c = makeOpnameWithNumber('OPM0003', '2026-09-20');

    jalankanMigrasiNomor();

    expect($a->fresh()->opname_number)->toBe('OPM202609160001')
        ->and($b->fresh()->opname_number)->toBe('OPM202609160002')
        ->and($c->fresh()->opname_number)->toBe('OPM202609200001');
});

it('membiarkan nomor data demo apa adanya', function () {
    $ro = makeReceiveOrderWithNumber('RO-SPK-0007');

    jalankanMigrasiNomor();

    expect($ro->fresh()->receive_order_number)->toBe('RO-SPK-0007');
});

it('melanjutkan urutan dengan benar sesudah migrasi', function () {
    makeReceiveOrderWithNumber('RO'.today()->format('Ymd').'-0004');

    jalankanMigrasiNomor();

    expect(ReceiveOrder::nextNumber())->toBe('RO'.today()->format('Ymd').'0005');
});

it('aman dijalankan dua kali', function () {
    $ro = makeReceiveOrderWithNumber('RO20260916-0012');
    $opname = makeOpnameWithNumber('OPM0001', '2026-09-16');

    jalankanMigrasiNomor();
    jalankanMigrasiNomor();

    expect($ro->fresh()->receive_order_number)->toBe('RO202609160012')
        ->and($opname->fresh()->opname_number)->toBe('OPM202609160001')
        ->and(DB::table('medicine_stock_opnames')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Fixture lokal: dokumen dengan nomor yang ditentukan
|--------------------------------------------------------------------------
*/

function makeReceiveOrderWithNumber(string $nomor): ReceiveOrder
{
    static $seq = 0;
    $seq++;

    return ReceiveOrder::create([
        'receive_order_number' => $nomor,
        'supplier_id' => test()->supplier->id,
        'invoice_number' => 'INV-NUM-'.$seq,
        'receive_date' => today()->toDateString(),
    ]);
}

function makePurchaseOrderWithNumber(string $nomor): PurchaseOrder
{
    return PurchaseOrder::create([
        'po_number' => $nomor,
        'supplier_id' => test()->supplier->id,
        'po_date' => today()->toDateString(),
    ]);
}

function makeOpnameWithNumber(string $nomor, string $tanggal): MedicineStockOpname
{
    return MedicineStockOpname::create([
        'opname_number' => $nomor,
        'opname_date' => $tanggal,
    ]);
}

it('memberi nomor penjualan sesuai tanggal penjualannya bila tanggalnya diberikan', function () {
    $nomor = OrderForm::generateOrderCode(Carbon::parse('2026-05-17'));

    expect($nomor)->toBe('ORD202605170001');
});

it('menanggalkan ulang nomor penjualan simulasi sesuai tanggal penjualannya', function () {
    $mei = makeSimulatedOrder('ORD-202609210001', '2026-05-17');
    $meiKedua = makeSimulatedOrder('ORD-202609210002', '2026-05-17');
    $juni = makeSimulatedOrder('ORD-202609210003', '2026-06-02');

    jalankanMigrasiNomor();

    expect($mei->fresh()->order_code)->toBe('ORD202605170001')
        ->and($meiKedua->fresh()->order_code)->toBe('ORD202605170002')
        ->and($juni->fresh()->order_code)->toBe('ORD202606020001');
});

it('tidak menyentuh penjualan yang diinput lewat form walau tanggalnya mundur', function () {
    $form = Order::create([
        'order_code' => 'ORD-202609210001',
        'order_date' => '2026-05-17', // dimundurkan kasir; nomor tetap tanggal entri
        'grand_total' => 0,
    ]);

    jalankanMigrasiNomor();

    expect($form->fresh()->order_code)->toBe('ORD202609210001');
});

it('tidak menabrak nomor penjualan lain di hari yang sama saat menanggalkan ulang', function () {
    $riil = Order::create(['order_code' => 'ORD-202605170001', 'order_date' => '2026-05-17', 'grand_total' => 0]);
    $simulasi = makeSimulatedOrder('ORD-202609210001', '2026-05-17');

    jalankanMigrasiNomor();

    expect($riil->fresh()->order_code)->toBe('ORD202605170001')
        ->and($simulasi->fresh()->order_code)->toBe('ORD202605170002');
});

it('aman menanggalkan ulang dua kali', function () {
    $simulasi = makeSimulatedOrder('ORD-202609210001', '2026-05-17');

    jalankanMigrasiNomor();
    jalankanMigrasiNomor();

    expect($simulasi->fresh()->order_code)->toBe('ORD202605170001')
        ->and(Order::count())->toBe(1);
});

function makeSimulatedOrder(string $nomor, string $tanggal): Order
{
    return Order::create([
        'order_code' => $nomor,
        'order_date' => $tanggal,
        'grand_total' => 0,
        'note' => '[SIMULASI] penjualan otomatis untuk demo & data C2 SAW bukan transaksi riil',
    ]);
}

it('tetap mengenali penjualan simulasi walau kalimat catatannya versi lama', function () {
    // Kalimat catatan pernah berubah (em dash dibersihkan di e4e953a), jadi baris lama
    // menyimpan teks yang tidak lagi sama dengan konstanta di kode. Penandanya awalan.
    $lama = Order::create([
        'order_code' => 'ORD-202609210001',
        'order_date' => '2026-05-17',
        'grand_total' => 0,
        'note' => '[SIMULASI] penjualan otomatis untuk demo & data C2 SAW kalimat versi lama',
    ]);

    jalankanMigrasiNomor();

    expect($lama->fresh()->order_code)->toBe('ORD202605170001');
});
