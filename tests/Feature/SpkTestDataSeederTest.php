<?php

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\ReceiveOrderItem;
use App\Services\SawCalculationService;
use App\Services\StockCardService;
use Database\Seeders\SawCriteriaSeeder;
use Database\Seeders\SpkTestDataSeeder;

/**
 * Seeder demo SPK (rencana E6, T2): idempoten, lewat jalur service, dan menyebar ke
 * kelima bracket tiap kriteria supaya ranking Bab IV punya variasi.
 */
beforeEach(function () {
    $this->seed(SawCriteriaSeeder::class);
    @unlink(storage_path('app/spk-test-data.json'));
});

afterEach(fn () => @unlink(storage_path('app/spk-test-data.json')));

it('membangun data demo yang konsisten, idempoten, dan tersebar di semua bracket', function () {
    $this->seed(SpkTestDataSeeder::class);

    expect(Medicine::count())->toBe(150)
        ->and(ReceiveOrderItem::count())->toBeGreaterThan(150)
        ->and(MedicineStock::layers()->whereNull('expired_date')->count())->toBe(0);

    // Tidak ada lapisan negatif dan tidak ada baris C tanpa lapisan (semua lewat FEFO).
    $stockCard = app(StockCardService::class);
    foreach (Medicine::pluck('id') as $id) {
        foreach ($stockCard->layers($id) as $layer) {
            expect($layer->remaining)->toBeGreaterThanOrEqual(0);
        }
    }
    expect(MedicineStock::where('type_account', 'C')->whereNull('layer_stock_id')->count())->toBe(0);

    // Faktur ≤ 14 baris (R10).
    $maxLines = ReceiveOrderItem::selectRaw('receive_order_id, COUNT(*) n')->groupBy('receive_order_id')->get()->max('n');
    expect($maxLines)->toBeLessThanOrEqual(14);

    // SAW berjalan dan setiap kriteria punya minimal 4 dari 5 skor yang terisi.
    $calc = app(SawCalculationService::class)->execute(today()->subDays(29), today());
    expect($calc->total_alternatives)->toBe(150)->and($calc->excluded_count)->toBe(0);
    foreach (['c1_score', 'c2_score', 'c3_score', 'c4_score'] as $col) {
        expect($calc->results->pluck($col)->unique()->count())->toBeGreaterThanOrEqual(4);
    }
    // Ada obat berstok tersedia 0 (T2) dan tetap punya C4.
    $zero = $calc->results->firstWhere('c1_stock', 0);
    expect($zero)->not->toBeNull()->and((int) $zero->c4_raw)->toBeGreaterThan(0)->and((int) $zero->c3_raw)->toBe(0);

    // Idempoten: dijalankan lagi → jumlah obat tidak berlipat.
    $this->seed(SpkTestDataSeeder::class);
    expect(Medicine::count())->toBe(150);
});
