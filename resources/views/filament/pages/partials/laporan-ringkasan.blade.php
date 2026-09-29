{{--
    Kartu ringkasan laporan.

    Isinya datang dari `ringkasan()` milik halaman (trait LaporanSeragam), jadi angka yang tampil di
    layar sama persis dengan yang tercetak di Excel dan PDF. Bentuk kartunya mengikuti blok ringkasan
    di laporan Rekap dan Fast/Slow Moving.
--}}
@if (! empty($ringkasan))
    <x-filament::section>
        <x-slot name="heading">Ringkasan</x-slot>
        <x-slot name="description">{{ $periode }}</x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($ringkasan as $label => $nilai)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-xs uppercase tracking-wide text-gray-600 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $nilai }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
@endif
