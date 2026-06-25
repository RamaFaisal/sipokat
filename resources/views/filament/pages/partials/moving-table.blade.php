<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">#</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Kode</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Nama Obat</th>
                <th class="px-3 py-2 text-left font-medium text-gray-700 dark:text-gray-300">Kategori</th>
                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Stok Saat Ini</th>
                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Qty Terjual</th>
                <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Demand/Bulan</th>
                @if (! ($hideValue ?? false))
                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-300">Nilai Penjualan</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach ($rows as $i => $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                    <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                    <td class="px-3 py-2 text-xs font-mono text-gray-600 dark:text-gray-400">{{ $row['code'] }}</td>
                    <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row['name'] }}</td>
                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['category'] }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['current_stock']) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['total_qty']) }}</td>
                    <td class="px-3 py-2 text-right">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium tabular-nums
                            @if ($badgeColor === 'green') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                            @elseif ($badgeColor === 'amber') bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300
                            @elseif ($badgeColor === 'red') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ number_format($row['monthly_avg']) }}
                        </span>
                    </td>
                    @if (! ($hideValue ?? false))
                        <td class="px-3 py-2 text-right tabular-nums">Rp {{ number_format($row['total_value'], 0, ',', '.') }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
