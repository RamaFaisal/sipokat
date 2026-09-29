{{-- Tab menu Laporan: satu entri sidebar, laporan lain dicapai dari sini (K7). --}}
@php($tabs = \App\Support\MenuLaporan::untukPengguna($halamanAktif))

@if (count($tabs) > 1)
    <nav class="fi-tabs flex flex-wrap gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        @foreach ($tabs as $tab)
            <a href="{{ $tab['url'] }}"
               @class([
                   'whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition',
                   'bg-primary-600 text-white shadow' => $tab['aktif'],
                   'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' => ! $tab['aktif'],
               ])>
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
@endif
