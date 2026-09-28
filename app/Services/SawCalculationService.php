<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\OrderItem;
use App\Models\SawCriteria;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
 *
 * Sejak 2026-09-27 hasil perhitungan **tidak disimpan**: halaman dan widget memanggil
 * calculateCached() sehingga angka yang tampil selalu mencerminkan kondisi saat itu juga.
 * Cache hanya memakai ulang hasil selama masukannya belum berubah, bukan menyimpan riwayat.
 * Riwayat snapshot dihapus bukti angka untuk naskah dibekukan sebagai berkas terpisah.
 */
class SawCalculationService
{
    public const CRITERIA_CODES = ['C1', 'C2', 'C3', 'C4'];

    /** Jaring pengaman bila cap kondisi meleset; invalidasi sebenarnya lewat stateStamp(). */
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private StockCardService $stockCard,
    ) {}

    /**
     * Versi calculate() untuk halaman dan widget: hasil dipakai ulang selama dasar
     * hitungannya belum berubah.
     *
     * Perlu karena calculate() memakai 4 + 5N query (127 obat = sekitar 639), sedangkan
     * halaman SAW menghitung ulang tiap render Livewire (ganti periode, sort, centang baris)
     * dan widget dashboard ikut memanggilnya tiap dashboard dibuka.
     *
     * Kunci memuat periode + cap kondisi kartu stok, master obat, dan kriteria, jadi begitu
     * ada penerimaan, penjualan, opname, perubahan batas minimum, atau perubahan bobot,
     * kuncinya berubah dan perhitungan diulang. Angka yang tampil tetap kondisi terkini
     * (keputusan 2026-09-27); yang dihindari hanya menghitung ulang hal yang sama persis.
     */
    public function calculateCached(Carbon $periodStart, Carbon $periodEnd): array
    {
        $key = 'saw:ranking:'.md5(implode('|', [
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $this->stateStamp(),
        ]));

        return Cache::remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->calculate($periodStart, $periodEnd),
        );
    }

    /**
     * Cap kondisi seluruh masukan SAW dalam tiga query murah.
     *
     * `updated_at` saja tidak cukup: kolomnya berpresisi detik, sedangkan penyuntingan lalu
     * render ulang Livewire terjadi dalam detik yang sama (ubah bobot → tabel langsung
     * digambar ulang). Karena itu dua tabel kecil disidik isinya, dan kartu stok yang bisa
     * ribuan baris diwakili agregat kolom yang benar-benar dipakai SAW: jumlah baris
     * menangkap penghapusan (baris ledger dihapus sungguhan, B5), id terakhir menangkap
     * penambahan, sum(qty)/sum(hpp_avg)/max(expired_date) menangkap penyuntingan di tempat
     * (mis. syncReceipt mengubah harga lalu replayHpp menulis ulang hpp_avg).
     */
    private function stateStamp(): string
    {
        $ledger = DB::table('medicine_stocks')->selectRaw(
            'count(*) as jumlah, max(id) as id_terakhir, max(updated_at) as diubah,'
            .' sum(qty) as total_qty, sum(hpp_avg) as total_hpp, max(expired_date) as ed_terjauh'
        )->first();

        $medicines = DB::table('medicines')->orderBy('id')
            ->get(['id', 'min_stock', 'status', 'deleted_at']);

        $criteria = DB::table('saw_criteria')->orderBy('id')->get();

        return implode('|', [
            json_encode($ledger),
            md5((string) json_encode($medicines)),
            md5((string) json_encode($criteria)),
        ]);
    }

    /**
     * Hitung peringkat SAW atas kondisi saat ini. **Tidak menyimpan apa pun** hasilnya
     * dipakai langsung oleh halaman & widget, sehingga angka yang dilihat selalu mutakhir
     * tanpa perlu snapshot (keputusan peneliti 2026-09-27).
     *
     * @return array{
     * period_start: Carbon,
     * period_end: Carbon,
     * calculated_at: Carbon,
     * criteria: array<int, array<string, mixed>>,
     * column_stats: array<string, array{min: int, max: int}>,
     * total_alternatives: int,
     * excluded_count: int,
     * rows: array<int, array<string, mixed>>,
     * }
     */
    public function calculate(Carbon $periodStart, Carbon $periodEnd): array
    {
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

        $byId = $medicines->keyBy('id');

        $rows = [];
        foreach ($ranked as $entry) {
            $mid = $entry['medicine_id'];
            /** @var Medicine|null $medicine */
            $medicine = $byId->get($mid);

            $rows[] = [
                // Filament mewajibkan kunci unik per baris untuk tabel berbasis array (ArrayRecord).
                '__key' => (string) $mid,
                'medicine_id' => $mid,
                'code' => $medicine?->code,
                'name' => $medicine?->name,
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
            ];
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'calculated_at' => now(),
            'criteria' => $criteria->values()
                ->map(fn (SawCriteria $c) => $c->only(['code', 'name', 'type', 'weight', 'scale_rules']))
                ->all(),
            'column_stats' => $this->columnStats($matrix, $criteria),
            'total_alternatives' => $medicines->count(),
            'excluded_count' => $excludedCount,
            'rows' => $rows,
        ];
    }

    /**
     * Min/Max skor per kriteria dari seluruh alternatif (K10) acuan normalisasi yang
     * ditampilkan di rincian perhitungan. Skor 0 dikecualikan dari Min (K15).
     *
     * @return array<string, array{min: int, max: int}>
     */
    protected function columnStats(array $matrix, Collection $criteria): array
    {
        $stats = [];

        foreach ($criteria as $code => $criterion) {
            $scores = array_map(fn ($row) => (int) ($row['score'][$code] ?? 0), $matrix);
            $positive = array_filter($scores, fn ($s) => $s > 0);

            $stats[$code] = [
                'min' => $positive === [] ? 0 : min($positive),
                'max' => $scores === [] ? 0 : max($scores),
            ];
        }

        return $stats;
    }

    /**
     * Empat kriteria aktif dengan Σ bobot tepat 1,000 (K7) dijaga di sini, bukan hanya di UI,
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
     * bilangan bulat menutup T5 (jalur terjadwal dulu menghitung 31,99 hari).
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
     * K9: peringkat padat Vi sama (6 desimal) → tingkat sama, tingkat berikutnya +1.
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
