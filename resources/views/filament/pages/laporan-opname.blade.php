<x-filament-panels::page>
    @include('filament.pages.partials.laporan-tabs', ['halamanAktif' => static::class])

    <x-filament::section>
        <x-slot name="heading">Filter Periode</x-slot>
        <x-slot name="description">Tabel langsung menyesuaikan begitu filter diubah. Klik "Export Excel" atau "Export PDF" untuk mengunduh seluruh barisnya.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @include('filament.pages.partials.laporan-ringkasan', [
        'ringkasan' => $this->ringkasan(),
        'periode' => $this->labelPeriode(),
    ])

    <x-filament::section>
        <x-slot name="heading">Penyesuaian Stok Opname</x-slot>
        <x-slot name="description">Selisih fisik terhadap sistem, satu baris per batch yang disesuaikan.</x-slot>

        @php($halaman = $this->halaman())

        @include('filament.pages.partials.laporan-pencarian', ['paginator' => $halaman, 'petunjuk' => 'Cari nomor opname, obat, atau batch'])

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2">Nomor Opname</th>
                        <th class="px-3 py-2">Tanggal</th>
                        <th class="px-3 py-2">Obat</th>
                        <th class="px-3 py-2">Batch</th>
                        <th class="px-3 py-2">Arah</th>
                        <th class="px-3 py-2 text-right">Selisih</th>
                        <th class="px-3 py-2 text-right">Nilai Selisih</th>
                        <th class="px-3 py-2">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($halaman as $row)
                        <tr class="dark:text-gray-300">
                            <td class="px-3 py-2 font-mono text-xs">{{ $row['nomor'] }}</td>
                            <td class="px-3 py-2">{{ $row['tanggal']?->translatedFormat(\App\Support\Tanggal::TAMPIL) }}</td>
                            <td class="px-3 py-2">{{ $row['obat'] }}</td>
                            <td class="px-3 py-2">{{ $row['batch'] }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'rounded-md px-2 py-0.5 text-xs font-medium',
                                    'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $row['arah'] === 'D',
                                    'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $row['arah'] !== 'D',
                                ])>
                                    {{ $row['arah'] === 'D' ? 'Lebih' : 'Kurang' }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                            <td @class([
                                'px-3 py-2 text-right font-semibold',
                                'text-success-600' => $row['nilai'] >= 0,
                                'text-danger-600' => $row['nilai'] < 0,
                            ])>
                                {{ $row['nilai'] < 0 ? '- ' : '+ ' }}Rp {{ number_format(abs($row['nilai']), 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-2 text-gray-500">{{ $row['note'] ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-gray-500">Belum ada penyesuaian opname pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @include('filament.pages.partials.laporan-paginasi', ['paginator' => $halaman])
    </x-filament::section>
</x-filament-panels::page>
