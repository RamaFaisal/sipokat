<x-filament::page>
    @include('filament.pages.partials.laporan-tabs', ['halamanAktif' => static::class])

    <x-filament::section>
        <x-slot name="heading">Filter Periode</x-slot>
        <x-slot name="description">Tabel langsung menyesuaikan begitu filter diubah. Klik "Export Excel" atau "Export PDF" untuk mengunduh seluruh barisnya.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @if ($this->summary->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Ringkasan {{ $this->summary['periode'] }}</x-slot>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @if ($this->summary['tipe'] !== 'pembelian')
                    <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                        <div class="text-xs uppercase tracking-wide text-green-700 dark:text-green-300">Total Penjualan</div>
                        <div class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">
                            Rp {{ number_format($this->summary['total_jual'], 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                            {{ $this->summary['jumlah_transaksi_jual'] }} transaksi &middot; {{ $this->summary['total_jual_qty'] }} unit
                        </div>
                    </div>
                @endif

                @if ($this->summary['tipe'] !== 'penjualan')
                    <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                        <div class="text-xs uppercase tracking-wide text-blue-700 dark:text-blue-300">Total Pembelian</div>
                        <div class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">
                            Rp {{ number_format($this->summary['total_beli'], 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                            {{ $this->summary['jumlah_transaksi_beli'] }} transaksi &middot; {{ $this->summary['total_beli_qty'] }} unit
                        </div>
                    </div>
                @endif

                @if ($this->summary['tipe'] === 'keduanya')
                    <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <div class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Margin Kotor (Estimasi)</div>
                        <div class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-1">
                            Rp {{ number_format($this->summary['margin_kotor'], 0, ',', '.') }}
                        </div>
                        <div class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                            Nilai Jual &minus; HPP Penjualan
                        </div>
                    </div>
                @endif

                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-700 dark:text-gray-300">Item Obat</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">
                        {{ $this->rows->count() }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                        Obat dengan transaksi dalam periode
                    </div>
                </div>
            </div>
        </x-filament::section>

        @if ($this->rows->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Detail Per Obat</x-slot>
                <x-slot name="description">Diurutkan dari nilai tertinggi pada sisi yang sedang dipilih. Kolom mengikuti tipe laporan.</x-slot>

                @php($halaman = $this->halaman())

                @include('filament.pages.partials.laporan-pencarian', ['paginator' => $halaman, 'petunjuk' => 'Cari kode, nama obat, atau kategori'])

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Kode</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Nama Obat</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Kategori</th>
                                @if ($this->tampilBeli())
                                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Qty Beli</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Nilai Beli</th>
                                @endif
                                @if ($this->tampilJual())
                                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Qty Jual</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Nilai Jual</th>
                                @endif
                                @if ($this->tampilMargin())
                                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Margin</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($halaman as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-600 dark:text-gray-400">{{ $row['code'] }}</td>
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['category'] }}</td>
                                    @if ($this->tampilBeli())
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['beli_qty']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['beli_nilai'], 0, ',', '.') }}</td>
                                    @endif
                                    @if ($this->tampilJual())
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['jual_qty']) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['jual_nilai'], 0, ',', '.') }}</td>
                                    @endif
                                    @if ($this->tampilMargin())
                                        <td class="px-3 py-2 text-right tabular-nums font-medium @if ($row['margin_kotor'] >= 0) text-green-600 @else text-red-600 @endif">
                                            {{ number_format($row['margin_kotor'], 0, ',', '.') }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @include('filament.pages.partials.laporan-paginasi', ['paginator' => $halaman])
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-400 italic">
                    Tidak ada transaksi dalam periode yang dipilih.
                </p>
            </x-filament::section>
        @endif
    @endif
</x-filament::page>
