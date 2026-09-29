<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.pages.partials.laporan-tabs', ['halamanAktif' => static::class])

        {{ $this->table }}
    </div>
</x-filament-panels::page>
