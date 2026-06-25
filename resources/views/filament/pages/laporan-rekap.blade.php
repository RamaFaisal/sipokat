<x-filament::page>
    <x-filament::section>
        <x-slot name="heading">Filter Periode</x-slot>
        <x-slot name="description">Pilih rentang tanggal + tipe laporan, lalu klik "Tampilkan Laporan" di header. Klik "Export Excel" untuk download.</x-slot>
        {{ $this->form }}
    </x-filament::section>

    @if ($this->summary->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Ringkasan — {{ $this->summary['periode'] }}</x-slot>

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
                <x-slot name="heading">Detail Per Obat (Top {{ min(50, $this->rows->count()) }})</x-slot>
                <x-slot name="description">Diurutkan berdasarkan nilai penjualan tertinggi. Export Excel untuk data lengkap.</x-slot>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Kode</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Nama Obat</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Kategori</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Qty Beli</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Nilai Beli</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Qty Jual</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Nilai Jual</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Margin</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($this->rows->take(50) as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-3 py-2 text-xs font-mono text-gray-600 dark:text-gray-400">{{ $row['code'] }}</td>
                                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['category'] }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['beli_qty']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['beli_nilai'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['jual_qty']) }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['jual_nilai'], 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right tabular-nums font-medium @if ($row['margin_kotor'] >= 0) text-green-600 @else text-red-600 @endif">
                                        {{ number_format($row['margin_kotor'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($this->rows->count() > 50)
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 italic">
                        Menampilkan 50 dari {{ $this->rows->count() }} obat. Export Excel untuk data lengkap.
                    </p>
                @endif
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
