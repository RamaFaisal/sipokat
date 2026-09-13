<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SawCriteria;
use App\Services\SawCalculationService;
use Database\Seeders\SawCriteriaSeeder;
use Illuminate\Support\Carbon;

/**
 * Karakterisasi SAW (rencana-revisi-2026-09 Bagian 7). Contoh 5 alternatif di bawah adalah
 * calon contoh Bab 3.4.4 versi baru: skala wawancara, C1 rasio, C4 HPP, peringkat padat.
 * Setiap sel dihitung manual di komentar supaya bisa dicek penguji.
 */
beforeEach(function () {
    seedMasterFixtures();
    $this->seed(SawCriteriaSeeder::class);
    $this->service = app(SawCalculationService::class);
});

/**
 * Bangun satu alternatif: terima (stok + permintaan) @ harga, ED hari ini + $edDays,
 * lalu jual $demand dalam periode → stok tersedia = $stock, C2 = $demand (periode 30 hari), HPP = $price.
 */
function alternative(string $name, int $stock, int $minStock, int $demand, int $edDays, int $price)
{
    $m = makeMedicine(['name' => $name, 'min_stock' => $minStock]);
    $ro = makeReceiveOrder($m, $stock + $demand, today()->addDays($edDays)->toDateString(), 'B-'.$m->id);
    $ro->items()->update(['price' => $price]); // harga per alternatif (fixture bawaan 5.000)
    app(\App\Services\StockMovementService::class)->recordReceipt($ro);
    if ($demand > 0) {
        $order = Order::create(['order_code' => 'ORD-SAW-'.$m->id, 'order_date' => today()->toDateString(), 'grand_total' => 0]);
        OrderItem::create(['order_id' => $order->id, 'medicine_id' => $m->id, 'medicine_name' => $m->name, 'qty' => $demand, 'price' => $price * 2]);
        app(\App\Services\StockMovementService::class)->recordSale($order);
    }

    return $m;
}

it('mereproduksi contoh 5 alternatif (calon Bab 3.4.4) sel per sel', function () {
    //                    nama              stok min demand ED   HPP
    $a1 = alternative('A1 PARACETAMOL', 8, 20, 120, 450, 1800);
    $a2 = alternative('A2 AMOXICILLIN', 12, 20, 90, 450, 6500);
    $a3 = alternative('A3 SANGOBION', 45, 20, 25, 200, 12000);
    $a4 = alternative('A4 LANSOPRAZOLE', 30, 10, 60, 100, 30000);
    $a5 = alternative('A5 LIPITOR', 4, 6, 15, 800, 165000);

    $calc = $this->service->execute(today()->subDays(29), today());
    $r = $calc->results->keyBy('medicine_id');

    // Nilai mentah & skor (Tabel 3.5–3.8 versi wawancara; C1 = stok ÷ min)
    expect((float) $r[$a1->id]->c1_raw)->toBe(0.40)->and($r[$a1->id]->c1_score)->toBe(1)
        ->and((float) $r[$a2->id]->c1_raw)->toBe(0.60)->and($r[$a2->id]->c1_score)->toBe(1)
        ->and((float) $r[$a3->id]->c1_raw)->toBe(2.25)->and($r[$a3->id]->c1_score)->toBe(3)
        ->and((float) $r[$a4->id]->c1_raw)->toBe(3.00)->and($r[$a4->id]->c1_score)->toBe(3)
        ->and((float) $r[$a5->id]->c1_raw)->toBe(0.67)->and($r[$a5->id]->c1_score)->toBe(1);

    expect($r[$a1->id]->c2_score)->toBe(5)->and($r[$a2->id]->c2_score)->toBe(4)
        ->and($r[$a3->id]->c2_score)->toBe(2)->and($r[$a4->id]->c2_score)->toBe(3)->and($r[$a5->id]->c2_score)->toBe(2);

    expect((int) $r[$a1->id]->c3_raw)->toBe(450)->and($r[$a1->id]->c3_score)->toBe(4)
        ->and($r[$a3->id]->c3_score)->toBe(3)->and($r[$a4->id]->c3_score)->toBe(2)->and($r[$a5->id]->c3_score)->toBe(5);

    expect((int) $r[$a1->id]->c4_raw)->toBe(1800)->and($r[$a1->id]->c4_score)->toBe(1)
        ->and($r[$a2->id]->c4_score)->toBe(2)->and($r[$a3->id]->c4_score)->toBe(3)
        ->and($r[$a4->id]->c4_score)->toBe(3)->and($r[$a5->id]->c4_score)->toBe(5);

    // Normalisasi: C1 min/X (min 1), C2 X/max (max 5), C3 min/X (min 2), C4 min/X (min 1)
    expect((float) $r[$a3->id]->c1_norm)->toBe(0.333333)
        ->and((float) $r[$a2->id]->c2_norm)->toBe(0.8)
        ->and((float) $r[$a3->id]->c3_norm)->toBe(0.666667)
        ->and((float) $r[$a5->id]->c4_norm)->toBe(0.2);

    // Vi = 0,3·R1 + 0,3·R2 + 0,2·R3 + 0,2·R4
    // A1 = 0,3·1 + 0,3·1 + 0,2·0,5 + 0,2·1 = 0,90
    // A2 = 0,3·1 + 0,3·0,8 + 0,2·0,5 + 0,2·0,5 = 0,74
    // A3 = 0,3·0,333 + 0,3·0,4 + 0,2·0,667 + 0,2·0,333 = 0,42
    // A4 = 0,3·0,333 + 0,3·0,6 + 0,2·1 + 0,2·0,333 = 0,546667
    // A5 = 0,3·1 + 0,3·0,4 + 0,2·0,4 + 0,2·0,2 = 0,54
    expect((float) $r[$a1->id]->preference_value)->toBe(0.9)
        ->and((float) $r[$a2->id]->preference_value)->toBe(0.74)
        ->and((float) $r[$a3->id]->preference_value)->toBe(0.42)
        ->and((float) $r[$a4->id]->preference_value)->toBe(0.546667)
        ->and((float) $r[$a5->id]->preference_value)->toBe(0.54);

    // Tingkat: A1 (1), A2 (2), A4 (3), A5 (4), A3 (5)
    expect($r[$a1->id]->rank)->toBe(1)->and($r[$a2->id]->rank)->toBe(2)
        ->and($r[$a4->id]->rank)->toBe(3)->and($r[$a5->id]->rank)->toBe(4)->and($r[$a3->id]->rank)->toBe(5)
        ->and($calc->total_alternatives)->toBe(5)
        ->and($calc->excluded_count)->toBe(0);

    // Snapshot menyimpan stok & batas minimum supaya tabel menampilkan "8 / 20"
    expect($r[$a1->id]->c1_stock)->toBe(8)->and($r[$a1->id]->c1_min_stock)->toBe(20);
});

it('mengecualikan obat aktif tanpa riwayat kartu stok dan menghitungnya (K0)', function () {
    alternative('ADA LEDGER', 10, 20, 0, 300, 1000);
    makeMedicine(['name' => 'BARU TANPA LEDGER']);

    $calc = $this->service->execute(today()->subDays(29), today());

    expect($calc->total_alternatives)->toBe(1)->and($calc->excluded_count)->toBe(1);
});

it('memberi C3 = 0 hari (skor 1) dan C4 dari HPP terakhir untuk obat yang stoknya habis (T2, K4)', function () {
    $m = alternative('HABIS', 0, 20, 30, 300, 5000);

    $calc = $this->service->execute(today()->subDays(29), today());
    $row = $calc->results->firstWhere('medicine_id', $m->id);

    expect((int) $row->c3_raw)->toBe(0)->and($row->c3_score)->toBe(1)
        ->and((int) $row->c4_raw)->toBe(5000)->and($row->c4_score)->toBe(2)
        ->and((float) $row->c1_raw)->toBe(0.0)->and($row->c1_score)->toBe(1);
});

it('menolak perhitungan bila total bobot bukan 1,000 — juga di jalur service/terjadwal (K7)', function () {
    alternative('X', 10, 20, 0, 300, 1000);
    SawCriteria::where('code', 'C4')->update(['weight' => 0.100]);

    expect(fn () => $this->service->execute(today()->subDays(29), today()))
        ->toThrow(RuntimeException::class, '0.900');
});

it('menghitung pembagi hari sebagai bilangan bulat inklusif walau periode diberi jam (T5, K2)', function () {
    $m = alternative('PERIODE', 10, 20, 60, 300, 1000); // 60 terjual hari ini

    $calc = $this->service->execute(
        Carbon::today()->subDays(29)->startOfDay(),
        Carbon::today()->endOfDay(), // jalur terjadwal lama memberi 23:59:59
    );

    // 30 hari → 60 ÷ 30 × 30 = 60, bukan 58 (31,99 hari)
    expect((int) $calc->results->firstWhere('medicine_id', $m->id)->c2_raw)->toBe(60);
});

it('memberi tingkat yang sama untuk Vi identik dan mengurutkan dalam tingkat oleh rasio C1 terkecil (K9)', function () {
    // Tiga obat dengan skor identik di semua kriteria → Vi identik → tingkat 1 semua; tingkat berikutnya = 2.
    $a = alternative('SERI A', 18, 20, 5, 300, 1000); // rasio 0,90
    $b = alternative('SERI B', 4, 20, 5, 300, 1000);  // rasio 0,20 → tampil paling atas
    $c = alternative('SERI C', 10, 20, 5, 300, 1000); // rasio 0,50
    $d = alternative('BEDA', 60, 20, 5, 300, 1000);   // rasio 3,00 → skor 3 → Vi lebih rendah

    $calc = $this->service->execute(today()->subDays(29), today());
    $rows = $calc->results->sortBy('sort_order')->values();

    expect($rows->pluck('medicine_id')->all())->toBe([$b->id, $c->id, $a->id, $d->id])
        ->and($rows->pluck('rank')->all())->toBe([1, 1, 1, 2])
        ->and($rows->pluck('sort_order')->all())->toBe([1, 2, 3, 4]);
});
