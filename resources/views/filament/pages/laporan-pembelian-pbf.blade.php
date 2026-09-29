<x-filament-panels::page>
    @include('filament.pages.partials.laporan-tabs', ['halamanAktif' => static::class])

    <x-filament::section>
        <x-slot name="heading">Periode</x-slot>
        <x-slot name="description">Pilih rentang tanggal lalu klik "Tampilkan Laporan" di header. Klik "Export Excel" atau "Export PDF" untuk mengunduh.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @include('filament.pages.partials.laporan-ringkasan', [
        'ringkasan' => $this->ringkasan(),
        'periode' => $this->labelPeriode(),
    ])

    <x-filament::section>
        <x-slot name="heading">Pembelian per PBF</x-slot>
        <x-slot name="description">{{ $this->labelPeriode() }} &middot; total Rp {{ number_format($this->totalNilai(), 0, ',', '.') }}</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2">PBF</th>
                        <th class="px-3 py-2 text-right">Faktur</th>
                        <th class="px-3 py-2 text-right">Ragam Obat</th>
                        <th class="px-3 py-2 text-right">Jumlah Satuan</th>
                        <th class="px-3 py-2 text-right">Nilai Pembelian</th>
                        <th class="px-3 py-2 text-right">Porsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->rows as $row)
                        <tr class="dark:text-gray-300">
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $row['kode'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['nama'] }}</div>
                            </td>
                            <td class="px-3 py-2 text-right">{{ $row['faktur'] }}</td>
                            <td class="px-3 py-2 text-right">{{ $row['ragam_obat'] }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($row['jumlah'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold">Rp {{ number_format($row['nilai'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($this->porsi($row['nilai']), 1, ',', '.') }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada penerimaan pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
