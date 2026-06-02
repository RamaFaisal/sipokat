<x-filament::page>
    <x-filament::section>
        <x-slot name="heading">Parameter Perhitungan</x-slot>
        <x-slot name="description">Isi rentang periode untuk agregasi permintaan (C2), lalu tekan tombol "Hitung Sekarang" di header.</x-slot>

        {{ $this->form }}
    </x-filament::section>

    @if ($latest)
        <x-filament::section>
            <x-slot name="heading">
                Hasil Terakhir &mdash; {{ \Illuminate\Support\Carbon::parse($latest->calculated_at)->format('d M Y H:i') }}
            </x-slot>
            <x-slot name="description">
                Periode: {{ \Illuminate\Support\Carbon::parse($latest->period_start)->format('d M Y') }}
                s/d {{ \Illuminate\Support\Carbon::parse($latest->period_end)->format('d M Y') }}
                &middot; Total alternatif: {{ $latest->total_alternatives }}
                &middot; Trigger: {{ $latest->trigger_type }}
            </x-slot>

            {{ $this->table }}
        </x-filament::section>
    @else
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Belum ada perhitungan SAW. Tekan tombol <strong>"Hitung Sekarang"</strong> di header untuk menjalankan kalkulasi pertama.
            </p>
        </x-filament::section>
    @endif
</x-filament::page>
