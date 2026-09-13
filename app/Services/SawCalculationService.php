<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\OrderItem;
use App\Models\SawCalculation;
use App\Models\SawCalculationResult;
use App\Models\SawCriteria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SPK SAW (rencana-revisi-2026-09 Bagian 7).
 *
 * Alternatif = obat aktif yang punya minimal satu baris kartu stok (K0).
 * Nilai mentah: C1 = stok tersedia ÷ min_stock (K1), C2 = permintaan/30 hari (K2),
 * C3 = sisa hari ke ED batch terjauh yang bersisa, 0 bila stok tersedia 0 (K3),
 * C4 = HPP rata-rata bergerak (K4). Konversi skala 1–5 inklusif, normalisasi min/X
 * (cost) & X/max (benefit) dengan skor 0 dikecualikan dari Min (K15), Vi = Σ Wj·Rij,
 * peringkat padat + tie-breaker rasio C1 → permintaan → ED (K9).
 */
class SawCalculationService
{
    public const CRITERIA_CODES = ['C1', 'C2', 'C3', 'C4'];

    public function __construct(
        private StockCardService $stockCard,
    ) {}

    public function execute(
        Carbon $periodStart,
        Carbon $periodEnd,
        string $triggerType = 'manual',
        ?int $userId = null,
    ): SawCalculation {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->startOfDay();

        if ($periodStart->greaterThan($periodEnd)) {
            throw new \InvalidArgumentException('period_start harus lebih awal atau sama dengan period_end.');
        }

        $criteria = $this->activeCriteria();

        [$medicines, $excludedCount] = $this->alternatives();

        if ($medicines->isEmpty()) {
            throw new \RuntimeException('Tidak ada obat aktif dengan riwayat kartu stok untuk dihitung.');
        }

        $matrix = $this->buildDecisionMatrix($medicines, $criteria, $periodStart, $periodEnd);
        $normalized = $this->normalize($matrix, $criteria);
        $preferences = $this->calculatePreference($normalized, $criteria);
        $ranked = $this->rank($preferences, $matrix);

        return DB::transaction(function () use (
            $criteria, $medicines, $excludedCount, $matrix, $normalized, $ranked,
            $periodStart, $periodEnd, $triggerType, $userId,
        ) {
            $calc = SawCalculation::create([
                'calculated_at' => now(),
                'calculated_by' => $userId,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'trigger_type' => $triggerType,
                'criteria_snapshot' => $criteria->values()
                    ->map(fn (SawCriteria $c) => $c->only(['code', 'name', 'type', 'weight', 'scale_rules']))
                    ->all(),
                'total_alternatives' => $medicines->count(),
                'excluded_count' => $excludedCount,
            ]);

            $rows = [];
            foreach ($ranked as $entry) {
                $mid = $entry['medicine_id'];
                $rows[] = [
                    'saw_calculation_id' => $calc->id,
                    'medicine_id' => $mid,
                    'c1_raw' => $matrix[$mid]['raw']['C1'],
                    'c1_stock' => $matrix[$mid]['meta']['stock'],
                    'c1_min_stock' => $matrix[$mid]['meta']['min_stock'],
                    'c2_raw' => $matrix[$mid]['raw']['C2'],
                    'c3_raw' => $matrix[$mid]['raw']['C3'],
                    'c4_raw' => $matrix[$mid]['raw']['C4'],
                    'c1_score' => $matrix[$mid]['score']['C1'],
                    'c2_score' => $matrix[$mid]['score']['C2'],
                    'c3_score' => $matrix[$mid]['score']['C3'],
                    'c4_score' => $matrix[$mid]['score']['C4'],
                    'c1_norm' => round($normalized[$mid]['C1'], 6),
                    'c2_norm' => round($normalized[$mid]['C2'], 6),
                    'c3_norm' => round($normalized[$mid]['C3'], 6),
                    'c4_norm' => round($normalized[$mid]['C4'], 6),
                    'preference_value' => $entry['value'],
                    'rank' => $entry['rank'],
                    'sort_order' => $entry['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            SawCalculationResult::insert($rows);

            return $calc->fresh('results');
        });
    }

    /**
     * Empat kriteria aktif dengan Σ bobot tepat 1,000 (K7) — dijaga di sini, bukan hanya di UI,
     * supaya jalur terjadwal tidak bisa menghasilkan Vi di luar 0–1.
     *
     * @return Collection<string, SawCriteria>
     */
    public function activeCriteria(): Collection
    {
        $criteria = SawCriteria::where('is_active', true)->orderBy('sort_order')->get()->keyBy('code');

        $missing = array_diff(self::CRITERIA_CODES, $criteria->keys()->all());
        if (! empty($missing)) {
            throw new \RuntimeException('Kriteria SAW kurang lengkap. Hilang: '.implode(', ', $missing));
        }

        $total = round((float) $criteria->sum(fn (SawCriteria $c) => (float) $c->weight), 3);
        if (abs($total - 1.0) > 0.0005) {
            throw new \RuntimeException(sprintf('Total bobot kriteria harus 1,000 (sekarang %.3f).', $total));
        }

        return $criteria;
    }

    /**
     * K0: obat aktif yang punya ≥ 1 baris kartu stok. Yang tidak punya dikecualikan dan dihitung.
     *
     * @return array{0: Collection<int, Medicine>, 1: int}
     */
    public function alternatives(): array
    {
        $withLedger = MedicineStock::query()->distinct()->pluck('medicine_id')->all();

        $active = Medicine::query()->where('status', 'active')->with('unit')->orderBy('id')->get();
        $medicines = $active->whereIn('id', $withLedger)->values();

        return [$medicines, $active->count() - $medicines->count()];
    }

    /**
     * @return array{raw: int|float|null, meta: array<string, mixed>}
     */
    public function getRawValue(Medicine $medicine, string $code, Carbon $periodStart, Carbon $periodEnd): array
    {
        return match ($code) {
            'C1' => $this->stockRatio($medicine),
            'C2' => ['raw' => $this->getMonthlyDemand($medicine, $periodStart, $periodEnd), 'meta' => []],
            'C3' => ['raw' => $medicine->farthestExpiryDays(), 'meta' => []],
            'C4' => ['raw' => $this->stockCard->currentHpp($medicine->id), 'meta' => []],
            default => ['raw' => null, 'meta' => []],
        };
    }

    /** K1: stok tersedia ÷ min_stock, dua desimal. min_stock dijamin > 0 (M9). */
    private function stockRatio(Medicine $medicine): array
    {
        $stock = $this->stockCard->availableStock($medicine->id);
        $min = max(1, (int) $medicine->min_stock);

        return [
            'raw' => round($stock / $min, 2),
            'meta' => ['stock' => $stock, 'min_stock' => $min],
        ];
    }

    /**
     * K2: Σ qty penjualan dalam periode ÷ jumlah hari × 30. Hari = selisih tanggal (00:00) + 1,
     * bilangan bulat — menutup T5 (jalur terjadwal dulu menghitung 31,99 hari).
     */
    public function getMonthlyDemand(Medicine $medicine, Carbon $start, Carbon $end): int
    {
        $totalQty = (int) OrderItem::where('medicine_id', $medicine->id)
            ->whereHas('order', function ($q) use ($start, $end) {
                // Order yang dibatalkan = soft delete; whereHas sudah mengecualikannya.
                // whereDate (bukan whereBetween string) supaya kolom date yang tersimpan dengan jam tetap cocok.
                $q->whereDate('order_date', '>=', $start->toDateString())
                    ->whereDate('order_date', '<=', $end->toDateString());
            })
            ->sum('qty');

        $days = max(1, (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);

        return (int) round(($totalQty / $days) * 30);
    }

    public function buildDecisionMatrix(
        Collection $medicines,
        Collection $criteria,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): array {
        $matrix = [];

        foreach ($medicines as $medicine) {
            $raws = [];
            $scores = [];
            $meta = ['stock' => null, 'min_stock' => null];

            foreach ($criteria as $code => $criterion) {
                $value = $this->getRawValue($medicine, $code, $periodStart, $periodEnd);
                $raws[$code] = $value['raw'];
                $scores[$code] = $criterion->convertToScore($value['raw']);
                $meta = array_merge($meta, $value['meta']);
            }

            $matrix[$medicine->id] = [
                'raw' => $raws,
                'score' => $scores,
                'meta' => $meta,
            ];
        }

        return $matrix;
    }

    /** K15: min/X untuk cost, X/max untuk benefit; skor 0 dikecualikan dari Min dan dinormalisasi 0. */
    public function normalize(array $matrix, Collection $criteria): array
    {
        $normalized = [];

        foreach (array_keys($matrix) as $medicineId) {
            $normalized[$medicineId] = [];
        }

        foreach ($criteria as $code => $criterion) {
            $columnScores = array_map(fn ($row) => $row['score'][$code] ?? 0, $matrix);
            $isCost = $criterion->isCost();

            $max = max($columnScores);
            $positiveScores = array_filter($columnScores, fn ($s) => $s > 0);
            $min = ! empty($positiveScores) ? min($positiveScores) : 0;

            foreach ($matrix as $medicineId => $row) {
                $score = $row['score'][$code] ?? 0;

                if ($score <= 0) {
                    $normalized[$medicineId][$code] = 0;

                    continue;
                }

                // Presisi penuh di sini; pembulatan 6 desimal hanya saat disimpan/ditampilkan,
                // supaya Vi = Σ Wj·Rij tidak menyimpang karena pembulatan bertingkat.
                $normalized[$medicineId][$code] = $isCost
                    ? $min / $score
                    : ($max > 0 ? $score / $max : 0);
            }
        }

        return $normalized;
    }

    public function calculatePreference(array $normalized, Collection $criteria): array
    {
        $preferences = [];

        foreach ($normalized as $medicineId => $norms) {
            $v = 0.0;
            foreach ($criteria as $code => $criterion) {
                $v += (float) $criterion->weight * (float) ($norms[$code] ?? 0);
            }
            $preferences[$medicineId] = round($v, 6);
        }

        return $preferences;
    }

    /**
     * K9: peringkat padat — Vi sama (6 desimal) → tingkat sama, tingkat berikutnya +1.
     * Urutan tampil di dalam tingkat: rasio C1 terkecil → permintaan terbesar → ED terdekat → id.
     *
     * @return array<int, array{medicine_id: int, value: float, rank: int, sort_order: int}>
     */
    public function rank(array $preferences, array $matrix): array
    {
        $entries = [];
        foreach ($preferences as $mid => $value) {
            $entries[] = [
                'medicine_id' => (int) $mid,
                'value' => (float) $value,
                'c1' => (float) ($matrix[$mid]['raw']['C1'] ?? PHP_FLOAT_MAX),
                'c2' => (float) ($matrix[$mid]['raw']['C2'] ?? 0),
                'c3' => (float) ($matrix[$mid]['raw']['C3'] ?? PHP_FLOAT_MAX),
            ];
        }

        usort($entries, function (array $a, array $b) {
            return [$b['value'], $a['c1'], $b['c2'], $a['c3'], $a['medicine_id']]
                <=> [$a['value'], $b['c1'], $a['c2'], $b['c3'], $b['medicine_id']];
        });

        $ranked = [];
        $tier = 0;
        $previous = null;
        foreach ($entries as $i => $entry) {
            $key = number_format($entry['value'], 6, '.', '');
            if ($key !== $previous) {
                $tier++;
                $previous = $key;
            }
            $ranked[] = [
                'medicine_id' => $entry['medicine_id'],
                'value' => $entry['value'],
                'rank' => $tier,
                'sort_order' => $i + 1,
            ];
        }

        return $ranked;
    }
}
