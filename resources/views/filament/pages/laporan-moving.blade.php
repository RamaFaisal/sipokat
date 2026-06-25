<x-filament::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    @if ($this->meta)
        <x-filament::section>
            <x-slot name="heading">Ringkasan Analisis — {{ $this->meta['periode'] }}</x-slot>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="text-xs uppercase tracking-wide text-gray-600 dark:text-gray-400">Total Obat Aktif</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100 mt-1">{{ $this->meta['total_obat_aktif'] }}</div>
                </div>
                <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                    <div class="text-xs uppercase tracking-wide text-green-700 dark:text-green-300">Ada Transaksi</div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100 mt-1">{{ $this->meta['obat_dengan_transaksi'] }}</div>
                </div>
                <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                    <div class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300">Tanpa Transaksi</div>
                    <div class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-1">{{ $this->meta['obat_tanpa_transaksi'] }}</div>
                </div>
                <div class="p-4 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <div class="text-xs uppercase tracking-wide text-blue-700 dark:text-blue-300">Periode (Hari)</div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-1">{{ $this->meta['days'] }}</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Fast Moving (Demand Tertinggi)</x-slot>
            <x-slot name="description">Obat dengan rata-rata demand bulanan paling tinggi — prioritas untuk stok cukup.</x-slot>

            @if ($this->fastMoving->isNotEmpty())
                @include('filament.pages.partials.moving-table', ['rows' => $this->fastMoving, 'badgeColor' => 'green'])
            @else
                <p class="text-sm text-gray-500 italic">Belum ada data penjualan dalam periode ini.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Slow Moving (Demand &lt; 20/bulan)</x-slot>
            <x-slot name="description">Obat dengan penjualan rendah (per Tabel 3.6 proposal: score 1 = demand &lt; 20/bln) — pertimbangkan turunkan target stok.</x-slot>

            @if ($this->slowMoving->isNotEmpty())
                @include('filament.pages.partials.moving-table', ['rows' => $this->slowMoving, 'badgeColor' => 'amber'])
            @else
                <p class="text-sm text-gray-500 italic">Tidak ada obat slow moving dalam periode.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Dead Stock (Tidak Ada Transaksi)</x-slot>
            <x-slot name="description">Obat aktif tanpa penjualan sama sekali dalam periode — diurutkan dari stok tertinggi (modal nyangkut).</x-slot>

            @if ($this->deadStock->isNotEmpty())
                @include('filament.pages.partials.moving-table', ['rows' => $this->deadStock, 'badgeColor' => 'red', 'hideValue' => true])
            @else
                <p class="text-sm text-gray-500 italic">Tidak ada dead stock.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament::page>
