@php
    /** @var \App\Models\SawCalculation $record */
    $record = $this->record;
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Informasi Snapshot</x-slot>
        <x-slot name="description">Hasil ini di-snapshot saat perhitungan dijalankan. Bobot & skala konversi yang dipakai dibekukan untuk audit.</x-slot>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="text-xs text-gray-500 uppercase">ID</div>
                <div class="font-mono text-sm mt-1">#{{ $record->id }}</div>
            </div>
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="text-xs text-gray-500 uppercase">Dihitung</div>
                <div class="text-sm mt-1">{{ $record->calculated_at->format('d M Y H:i') }}</div>
            </div>
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="text-xs text-gray-500 uppercase">Trigger</div>
                <div class="text-sm mt-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                        @if ($record->trigger_type === 'manual') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                        @else bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 @endif">
                        {{ $record->trigger_type === 'manual' ? 'Manual' : 'Terjadwal' }}
                    </span>
                </div>
            </div>
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
                <div class="text-xs text-gray-500 uppercase">Total Alternatif</div>
                <div class="text-lg font-bold mt-1 tabular-nums">{{ $record->total_alternatives }}</div>
            </div>
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 col-span-2">
                <div class="text-xs text-gray-500 uppercase">Periode Analisis</div>
                <div class="text-sm mt-1">
                    {{ \Illuminate\Support\Carbon::parse($record->period_start)->format('d M Y') }}
                    &nbsp;s/d&nbsp;
                    {{ \Illuminate\Support\Carbon::parse($record->period_end)->format('d M Y') }}
                </div>
            </div>
            <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800 col-span-2">
                <div class="text-xs text-gray-500 uppercase">Dihitung Oleh</div>
                <div class="text-sm mt-1">{{ $record->user?->name ?? 'Sistem (scheduled)' }}</div>
            </div>
        </div>

        @if (! empty($record->criteria_snapshot))
            <div class="mt-4">
                <details class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                    <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">
                        Lihat Snapshot Kriteria (config saat perhitungan)
                    </summary>
                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100 dark:bg-gray-800">
                                <tr>
                                    <th class="px-2 py-1 text-left">Kode</th>
                                    <th class="px-2 py-1 text-left">Nama</th>
                                    <th class="px-2 py-1 text-left">Tipe</th>
                                    <th class="px-2 py-1 text-right">Bobot</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($record->criteria_snapshot as $c)
                                    <tr class="border-t border-gray-200 dark:border-gray-700">
                                        <td class="px-2 py-1 font-mono">{{ $c['code'] ?? '-' }}</td>
                                        <td class="px-2 py-1">{{ $c['name'] ?? '-' }}</td>
                                        <td class="px-2 py-1">{{ ucfirst($c['type'] ?? '-') }}</td>
                                        <td class="px-2 py-1 text-right tabular-nums">{{ number_format((float) ($c['weight'] ?? 0), 3) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Hasil Ranking</x-slot>
        <x-slot name="description">Klik tombol "Detail" pada setiap baris untuk breakdown V<sub>i</sub> per obat.</x-slot>
        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
