<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Infolist Section --}}
        <div>
            {{ $this->infolist }}
        </div>

        {{-- Table Section --}}
        <div class="mt-4">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>