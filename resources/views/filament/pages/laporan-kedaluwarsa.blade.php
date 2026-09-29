<x-filament-panels::page>
    @include('filament.pages.partials.laporan-tabs', ['halamanAktif' => static::class])

    <x-filament::section>
        <x-slot name="heading">Filter</x-slot>
        <x-slot name="description">Pilih rentang pantauan lalu klik "Tampilkan Laporan" di header. Klik "Export Excel" atau "Export PDF" untuk mengunduh.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @include('filament.pages.partials.laporan-ringkasan', [
        'ringkasan' => $this->ringkasan(),
        'periode' => $this->labelPeriode(),
    ])

    <x-filament::section>
        <x-slot name="heading">Batch Mendekati Kedaluwarsa</x-slot>
        <x-slot name="description">Batch yang masih bersisa, diurutkan dari yang paling mepet. Batch yang dibeli dua kali tetap satu baris; rinciannya ada di Kartu Stok.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700 text-left text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-3 py-2">Kode</th>
                        <th class="px-3 py-2">Nama Obat</th>
                        <th class="px-3 py-2">Batch</th>
                        <th class="px-3 py-2">Kedaluwarsa</th>
                        <th class="px-3 py-2 text-right">Sisa Hari</th>
                        <th class="px-3 py-2 text-right">Sisa Stok</th>
                        <th class="px-3 py-2 text-right">Nilai Terancam</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->rows as $row)
                        @php($warna = $this->warna($row['sisa_hari']))
                        <tr @class([
                            'dark:text-gray-300',
                            'bg-danger-50 dark:bg-danger-500/10' => $warna === 'danger',
                            'bg-warning-50 dark:bg-warning-500/10' => $warna === 'warning',
                            'bg-success-50 dark:bg-success-500/10' => $warna === 'success',
                        ])>
                            <td class="px-3 py-2 font-mono text-xs">{{ $row['code'] }}</td>
                            <td class="px-3 py-2">{{ $row['name'] }}</td>
                            <td class="px-3 py-2">{{ $row['batch'] }}</td>
                            <td class="px-3 py-2">{{ $row['ed']->translatedFormat(\App\Support\Tanggal::TAMPIL) }}</td>
                            <td class="px-3 py-2 text-right font-medium">
                                @if ($row['sisa_hari'] < 0)
                                    lewat {{ abs($row['sisa_hari']) }} hari
                                @else
                                    {{ $row['sisa_hari'] }} hari
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($row['sisa'], 0, ',', '.') }} {{ $row['unit'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold">Rp {{ number_format($row['nilai'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-gray-500">Tidak ada batch yang mendekati kedaluwarsa pada rentang ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
