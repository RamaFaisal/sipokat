@php($summary = $this->summary())

<x-filament::page>
    <x-filament::section>
        <x-slot name="heading">Parameter Perhitungan</x-slot>
        <x-slot name="description">Isi rentang periode untuk agregasi permintaan (C2). Peringkat di bawah dihitung dari kondisi stok dan penjualan saat halaman ini dibuka.</x-slot>

        {{ $this->form }}
    </x-filament::section>

    @if ($summary['error'])
        <x-filament::section>
            <p class="text-sm text-danger-600 dark:text-danger-400">
                Perhitungan tidak dapat dijalankan: {{ $summary['error'] }}
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">
                Peringkat Prioritas Restock - per {{ $summary['calculated_at']->translatedFormat(\App\Support\Tanggal::TAMPIL_JAM) }}
            </x-slot>
            <x-slot name="description">
                Periode permintaan: {{ $summary['period_start']->translatedFormat(\App\Support\Tanggal::TAMPIL) }}
                s/d {{ $summary['period_end']->translatedFormat(\App\Support\Tanggal::TAMPIL) }}
                &middot; Alternatif: {{ $summary['total_alternatives'] }} obat
                @if ($summary['excluded_count'] > 0)
                    &middot; <span class="text-warning-600">{{ $summary['excluded_count'] }} obat tanpa riwayat kartu stok dikecualikan</span>
                @endif
                &middot; dihitung langsung, bukan dari data tersimpan
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    @endif
</x-filament::page>
