{{--
    Kotak pencarian tabel laporan.

    Menyaring baris yang terlihat, bukan isi laporannya: ringkasan dan ekspor tetap seluruh baris
    periode yang dipilih. Yang menentukan isi laporan adalah filter di atas.
--}}
<div class="mb-4 flex flex-wrap items-center gap-3">
    <div class="w-full max-w-xs">
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input
                type="search"
                wire:model.live.debounce.400ms="pencarian"
                placeholder="{{ $petunjuk }}"
            />
        </x-filament::input.wrapper>
    </div>

    @if (trim($this->pencarian) !== '')
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ number_format($paginator->total(), 0, ',', '.') }} dari
            {{ number_format($this->rows->count(), 0, ',', '.') }} baris
        </span>
    @endif
</div>
