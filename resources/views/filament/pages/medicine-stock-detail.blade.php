<x-filament::page>
    <x-filament::card>
        <form wire:submit.prevent="applyFilters" class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-4">

            <div class="flex flex-col">
                <label class="text-sm font-medium py-2 dark:text-gray-300">Tahun</label>
                <select wire:model="year" class="w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:outline-none p-1.5">
                    @foreach (range(now()->year, now()->year - 10) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-sm font-medium py-2 dark:text-gray-300">Bulan</label>
                <select wire:model="month" class="w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:outline-none p-1.5">
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-sm font-medium py-2 dark:text-gray-300">Supplier</label>
                <select wire:model="selectedSupplier" class="w-full rounded border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 focus:outline-none p-1.5">
                    <option value="">Semua Supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-8">
                <x-filament::button type="submit">
                    Filter
                </x-filament::button>

                {{ $this->exportAction }}
            </div>     
        </form>


        {{-- Data Table --}}
        <div class="w-full overflow-x-auto">

            <table class="min-w-full w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            No</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Nomor Referensi</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Supplier
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Tanggal Stok</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Refer Table</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Debit</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Kredit</th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                            Stok</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-900 dark:divide-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs text-gray-500 uppercase tracking-wider dark:text-gray-400 font-bold"
                            colspan="4">Stok Awal</th>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="px-6 py-4 whitespace-nowrap dark:text-gray-300 font-bold">{{ number_format($firstStock, 0, ',', '.') }}</td>
                    </tr>

                    @forelse ($stocks as $index => $stock)
                        <tr class="dark:text-gray-300">
                            <td class="px-6 py-4 whitespace-nowrap">{{ $index + 1 }}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-mono">{{ $stock['reference_number'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock['supplier'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock['date'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap">{{ $stock['refer_table'] }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-success-600 dark:text-success-400">{{ $stock['debit'] > 0 ? number_format($stock['debit'], 0, ',', '.') : '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-danger-600 dark:text-danger-400">{{ $stock['credit'] > 0 ? number_format($stock['credit'], 0, ',', '.') : '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap font-bold">{{ number_format($stock['current_stock'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                                No data available.
                            </td>
                        </tr>
                    @endforelse

                    <tr>
                        <th class="px-6 py-3 text-left text-xs text-gray-500 uppercase tracking-wider dark:text-gray-400 font-bold"
                            colspan="4">Stok Akhir</th>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="px-6 py-4 whitespace-nowrap dark:text-gray-300 font-bold">{{ number_format($lastStock, 0, ',', '.') }}</td>
                    </tr>
                </tbody>

            </table>
        </div>
    </x-filament::card>
</x-filament::page>