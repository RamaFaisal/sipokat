<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\OrderItem;
use App\Models\SawCalculation;
use App\Models\SawCalculationResult;
use App\Models\SawCriteria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implementasi metode Simple Additive Weighting (SAW) untuk prioritas restock obat.
 *
 * Pipeline: getRawValue → convertToScore (via SawCriteria) → normalize → calculatePreference → rank.
 *
 * Mapping kolom result table: c1..c4 = SawCriteria.code "C1".."C4" (konvensi seeder).
 * Kalau admin menambah kriteria di luar C1..C4, butuh migration kolom baru.
 */
class SawCalculationService
{
    /** Kode kriteria yang dipersist sebagai kolom c1..c4 di saw_calculation_results. */
    public const CRITERIA_CODES = ['C1', 'C2', 'C3', 'C4'];

    /**
     * Orchestrator: hitung SAW untuk semua medicine aktif dan simpan snapshot.
     */
    public function execute(
        Carbon $periodStart,
        Carbon $periodEnd,
        string $triggerType = 'manual',
        ?int $userId = null,
    ): SawCalculation {
        if ($periodStart->greaterThan($periodEnd)) {
            throw new \InvalidArgumentException('period_start harus lebih awal atau sama dengan period_end.');
        }

        $criteria = SawCriteria::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('code');

        if ($criteria->isEmpty()) {
            throw new \RuntimeException('Tidak ada kriteria SAW aktif.');
        }

        $missingCodes = array_diff(self::CRITERIA_CODES, $criteria->keys()->all());
        if (! empty($missingCodes)) {
            throw new \RuntimeException('Kriteria SAW kurang lengkap. Hilang: ' . implode(', ', $missingCodes));
        }

        $medicines = Medicine::where('status', 'active')->get();

        if ($medicines->isEmpty()) {
            throw new \RuntimeException('Tidak ada obat aktif untuk dihitung.');
        }

        $matrix = $this->buildDecisionMatrix($medicines, $criteria, $periodStart, $periodEnd);
        $normalized = $this->normalize($matrix, $criteria);
        $preferences = $this->calculatePreference($normalized, $criteria);

        return DB::transaction(function () use (
            $criteria, $medicines, $matrix, $normalized, $preferences,
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
            ]);

            $rankedPairs = collect($preferences)
                ->map(fn ($v, $mid) => ['medicine_id' => (int) $mid, 'value' => (float) $v])
                ->sortByDesc('value')
                ->values();

            $rows = [];
            foreach ($rankedPairs as $i => $pair) {
                $mid = $pair['medicine_id'];
                $rows[] = [
                    'saw_calculation_id' => $calc->id,
                    'medicine_id' => $mid,
                    'c1_raw' => $matrix[$mid]['raw']['C1'] ?? null,
                    'c2_raw' => $matrix[$mid]['raw']['C2'] ?? null,
                    'c3_raw' => $matrix[$mid]['raw']['C3'] ?? null,
                    'c4_raw' => $matrix[$mid]['raw']['C4'] ?? null,
                    'c1_score' => $matrix[$mid]['score']['C1'] ?? 0,
                    'c2_score' => $matrix[$mid]['score']['C2'] ?? 0,
                    'c3_score' => $matrix[$mid]['score']['C3'] ?? 0,
                    'c4_score' => $matrix[$mid]['score']['C4'] ?? 0,
                    'c1_norm' => $normalized[$mid]['C1'] ?? 0,
                    'c2_norm' => $normalized[$mid]['C2'] ?? 0,
                    'c3_norm' => $normalized[$mid]['C3'] ?? 0,
                    'c4_norm' => $normalized[$mid]['C4'] ?? 0,
                    'preference_value' => $pair['value'],
                    'rank' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            SawCalculationResult::insert($rows);

            return $calc->fresh('results');
        });
    }

    /**
     * Ambil nilai mentah per kriteria per obat.
     * C2 (permintaan) di-normalisasi ke ekuivalen bulanan agar scale_rules tetap konsisten saat periode != 30 hari.
     */
    public function getRawValue(
        Medicine $medicine,
        string $code,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): int|float|null {
        return match ($code) {
            'C1' => $medicine->currentStock(),
            'C2' => $this->getMonthlyDemand($medicine, $periodStart, $periodEnd),
            'C3' => $medicine->nearestExpiryDays(),
            'C4' => $medicine->purchase_price !== null ? (float) $medicine->purchase_price : null,
            default => null,
        };
    }

    /**
     * Total qty terjual dalam periode, diproyeksikan ke ekuivalen bulanan (30 hari).
     * Order dengan status 'cancelled' di-exclude.
     */
    private function getMonthlyDemand(Medicine $medicine, Carbon $start, Carbon $end): int
    {
        $totalQty = (int) OrderItem::where('medicine_id', $medicine->id)
            ->whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                    ->where('status', '!=', 'cancelled');
            })
            ->sum('qty');

        $days = max(1, $start->diffInDays($end) + 1);

        return (int) round(($totalQty / $days) * 30);
    }

    /**
     * Bangun matriks keputusan: untuk setiap obat × kriteria, simpan nilai raw + score 1-5.
     * Return: [medicine_id => ['raw' => [code => value], 'score' => [code => int]]]
     */
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

            foreach ($criteria as $code => $criterion) {
                $raw = $this->getRawValue($medicine, $code, $periodStart, $periodEnd);
                $raws[$code] = $raw;
                $scores[$code] = $criterion->convertToScore($raw);
            }

            $matrix[$medicine->id] = [
                'raw' => $raws,
                'score' => $scores,
            ];
        }

        return $matrix;
    }

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

                $normalized[$medicineId][$code] = $isCost
                    ? $min / $score
                    : ($max > 0 ? $score / $max : 0);
            }
        }

        return $normalized;
    }

    /**
     * Hitung nilai preferensi Vi = Σ (Wj × Rij) per alternatif.
     * Return: [medicine_id => preference_value]
     */
    public function calculatePreference(array $normalized, Collection $criteria): array
    {
        $preferences = [];

        foreach ($normalized as $medicineId => $norms) {
            $v = 0.0;
            foreach ($criteria as $code => $criterion) {
                $weight = (float) $criterion->weight;
                $norm = (float) ($norms[$code] ?? 0);
                $v += $weight * $norm;
            }
            $preferences[$medicineId] = round($v, 6);
        }

        return $preferences;
    }
}
