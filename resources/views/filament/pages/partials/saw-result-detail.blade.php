@php
    /** @var \App\Models\SawCalculationResult $result */
    $criteria = collect($result->calculation->criteria_snapshot ?? [])->keyBy('code');
    $weights = $criteria->mapWithKeys(fn ($c) => [$c['code'] => (float) $c['weight']]);
    $codes = ['C1', 'C2', 'C3', 'C4'];

    $criteriaInfo = [
        'C1' => ['label' => 'Stok',              'raw_unit' => 'unit',  'raw_label' => 'Jumlah stok saat ini'],
        'C2' => ['label' => 'Permintaan',        'raw_unit' => '/bulan','raw_label' => 'Permintaan rata-rata per bulan'],
        'C3' => ['label' => 'Sisa Kedaluwarsa',  'raw_unit' => 'hari',  'raw_label' => 'Sisa hari hingga ED terdekat (FEFO)'],
        'C4' => ['label' => 'Harga Beli',        'raw_unit' => 'Rp',    'raw_label' => 'Harga beli per unit'],
    ];

    $vBreakdown = [];
    $vTotal = 0;
    foreach ($codes as $code) {
        $w = (float) ($weights[$code] ?? 0);
        $norm = (float) ($result->{strtolower($code) . '_norm'} ?? 0);
        $contribution = $w * $norm;
        $vTotal += $contribution;
        $vBreakdown[$code] = [
            'weight' => $w,
            'norm' => $norm,
            'contribution' => $contribution,
        ];
    }
@endphp

<div class="space-y-6 p-1">
    {{-- Identitas --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Peringkat</div>
            <div class="text-2xl font-bold text-amber-600 mt-1">#{{ $result->rank }}</div>
        </div>
        <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Kode</div>
            <div class="text-sm font-mono mt-1">{{ $result->medicine?->code ?? '-' }}</div>
        </div>
        <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 col-span-2">
            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nama Obat</div>
            <div class="font-medium mt-1">{{ $result->medicine?->name ?? '-' }}</div>
        </div>
        <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 col-span-2 sm:col-span-4">
            <div class="text-xs text-amber-700 dark:text-amber-300 uppercase tracking-wide">Nilai Prioritas (V<sub>i</sub>)</div>
            <div class="text-3xl font-bold text-amber-900 dark:text-amber-100 mt-1 tabular-nums">{{ number_format($result->preference_value, 4) }}</div>
        </div>
    </div>

    {{-- Tabel Matriks --}}
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Matriks Per Kriteria</h3>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">Kode</th>
                        <th class="px-3 py-2 text-left font-medium">Kriteria</th>
                        <th class="px-3 py-2 text-center font-medium">Tipe</th>
                        <th class="px-3 py-2 text-right font-medium">Bobot (W)</th>
                        <th class="px-3 py-2 text-right font-medium">Nilai Mentah (X)</th>
                        <th class="px-3 py-2 text-center font-medium">Skor (1-5)</th>
                        <th class="px-3 py-2 text-right font-medium">Normalisasi (R = X/max)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($codes as $code)
                        @php
                            $c = $criteria[$code] ?? null;
                            $info = $criteriaInfo[$code];
                            $raw = $result->{strtolower($code) . '_raw'};
                            $score = $result->{strtolower($code) . '_score'};
                            $norm = $result->{strtolower($code) . '_norm'};
                            $type = $c['type'] ?? '-';
                            $w = (float) ($c['weight'] ?? 0);
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-2 font-mono text-xs">{{ $code }}</td>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $c['name'] ?? $info['label'] }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $info['raw_label'] }}</div>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    @if ($type === 'cost') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                                    @elseif ($type === 'benefit') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                                    @else bg-gray-100 text-gray-800 @endif">
                                    {{ ucfirst($type) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format($w, 3) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">
                                @if ($raw === null)
                                    <span class="text-gray-400 italic">tidak ada data</span>
                                @elseif ($code === 'C4')
                                    Rp {{ number_format($raw, 0, ',', '.') }}
                                @else
                                    {{ number_format($raw, 0) }} {{ $info['raw_unit'] }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-bold
                                    @if ($score == 5) bg-green-100 text-green-700
                                    @elseif ($score == 4) bg-lime-100 text-lime-700
                                    @elseif ($score == 3) bg-yellow-100 text-yellow-700
                                    @elseif ($score == 2) bg-orange-100 text-orange-700
                                    @elseif ($score == 1) bg-red-100 text-red-700
                                    @else bg-gray-100 text-gray-500 @endif">
                                    {{ $score }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium">{{ number_format($norm, 4) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Rumus V_i --}}
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Perhitungan V<sub>i</sub> Step-by-Step</h3>
        <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 space-y-3">
            <div class="text-sm text-blue-900 dark:text-blue-100">
                <strong>Rumus:</strong> <code class="px-1.5 py-0.5 bg-white/60 dark:bg-black/30 rounded">V<sub>i</sub> = Σ (W<sub>j</sub> × R<sub>ij</sub>)</code>
            </div>
            <div class="font-mono text-sm space-y-1 text-blue-900 dark:text-blue-100">
                @foreach ($codes as $code)
                    @php $b = $vBreakdown[$code]; @endphp
                    <div class="flex flex-wrap items-baseline gap-1.5">
                        <span class="text-blue-600 dark:text-blue-400">{{ $loop->first ? 'V = ' : ' + ' }}</span>
                        <span>({{ number_format($b['weight'], 3) }} × {{ number_format($b['norm'], 4) }})</span>
                        <span class="text-blue-600 dark:text-blue-400">=</span>
                        <span class="font-bold">{{ number_format($b['contribution'], 6) }}</span>
                        <span class="text-xs text-blue-500 dark:text-blue-400 ml-2">[kontribusi {{ $code }}]</span>
                    </div>
                @endforeach
                <div class="border-t border-blue-300 dark:border-blue-700 pt-2 mt-2 flex items-baseline gap-2">
                    <span class="text-blue-600 dark:text-blue-400">V = </span>
                    <span class="text-lg font-bold">{{ number_format($vTotal, 6) }}</span>
                    <span class="text-xs text-blue-500 ml-2">(dibulatkan ke 4 desimal: <strong>{{ number_format($vTotal, 4) }}</strong>)</span>
                </div>
            </div>
        </div>
    </div>

    <div class="text-xs text-gray-500 dark:text-gray-400 italic px-1">
        ℹ️ Snapshot perhitungan #{{ $result->saw_calculation_id }} &middot;
        Periode: {{ \Illuminate\Support\Carbon::parse($result->calculation->period_start)->format('d M Y') }}
        s/d {{ \Illuminate\Support\Carbon::parse($result->calculation->period_end)->format('d M Y') }}
        &middot; Trigger: {{ $result->calculation->trigger_type }}
    </div>
</div>
