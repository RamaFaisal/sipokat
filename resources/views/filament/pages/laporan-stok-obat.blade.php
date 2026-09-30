<x-filament-panels::page>
    @include('filament.pages.partials.laporan-tabs', ['halamanAktif' => static::class])

    <x-filament::section>
        <x-slot name="heading">Periode</x-slot>
        <x-slot name="description">Tabel langsung menyesuaikan begitu periode diubah. Klik "Export Excel" atau "Export PDF" untuk mengunduh seluruh barisnya.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @include('filament.pages.partials.laporan-ringkasan', [
        'ringkasan' => $this->ringkasan(),
        'periode' => $this->labelPeriode(),
    ])

    <x-filament::section>
        <x-slot name="heading">Rekap Stok per Obat</x-slot>
        <x-slot name="description">{{ $this->labelPeriode() }} &middot; {{ $this->rows->count() }} obat bermutasi</x-slot>

        @php($halaman = $this->halaman())

        @include('filament.pages.partials.laporan-pencarian', ['paginator' => $halaman, 'petunjuk' => 'Cari kode atau nama obat'])

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2">Kode</th>
                        <th class="px-3 py-2">Nama Obat</th>
                        <th class="px-3 py-2 text-right">Stok Awal</th>
                        <th class="px-3 py-2 text-right">Masuk</th>
                        <th class="px-3 py-2 text-right">Keluar</th>
                        <th class="px-3 py-2 text-right">Stok Akhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($halaman as $row)
                        <tr class="dark:text-gray-300">
                            <td class="px-3 py-2 font-mono text-xs">{{ $row['code'] }}</td>
                            <td class="px-3 py-2">{{ $row['name'] }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($row['awal'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-success-600">{{ number_format($row['masuk'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-danger-600">{{ number_format($row['keluar'], 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format($row['akhir'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500">Tidak ada mutasi stok pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('filament.pages.partials.laporan-paginasi', ['paginator' => $halaman])
    </x-filament::section>
</x-filament-panels::page>
