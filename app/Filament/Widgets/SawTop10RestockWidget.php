<?php

namespace App\Filament\Widgets;

use App\Services\SawCalculationService;
use App\Support\Tanggal;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

/**
 * Peringkat prioritas restock, seluruh obat yang masuk perhitungan (K5).
 *
 * Dihitung langsung saat dashboard dibuka (2026-09-27), memakai `calculateCached()` sehingga
 * pembukaan berulang tidak mengulang 4 + 5N query.
 *
 * Tabelnya berbasis array (`records()`), bukan query Eloquent, jadi pencarian harus disaring
 * sendiri di sini; `->searchable()` bawaan Filament hanya bekerja pada kolom database.
 */
class SawTop10RestockWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    /** @var array<string, mixed>|null */
    protected ?array $hasil = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Prioritas Restock (SAW)')
            ->description(fn (): string => $this->keterangan())
            // Penyaringan dilakukan **di dalam** closure, bukan sebelum tabel dibangun: objek tabel
            // di-cache oleh Filament, sehingga baris yang dihitung di luar closure akan tetap berisi
            // hasil render pertama dan kotak pencarian tidak pernah berpengaruh.
            ->records(fn (): Collection => collect($this->saring($this->hasil()['rows'])))
            ->searchable()
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir'])
            ->columns([
                TextColumn::make('rank')
                    ->label('Tingkat')
                    ->tooltip('Nilai prioritas sama = tingkat sama')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state <= 3 => 'danger',
                        (int) $state <= 7 => 'warning',
                        default => 'gray',
                    })
                    ->width(1),
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->wrap(),
            ]);
    }

    /** Hasil SAW untuk render ini; dihitung sekali lalu dipakai ulang dalam satu permintaan. */
    protected function hasil(): array
    {
        try {
            return $this->hasil ??= app(SawCalculationService::class)
                ->calculateCached(today()->subDays(29), today());
        } catch (\Throwable $e) {
            return $this->hasil ??= ['rows' => [], 'galat' => $e->getMessage()];
        }
    }

    protected function keterangan(): string
    {
        $hasil = $this->hasil();

        if (isset($hasil['galat'])) {
            return 'Perhitungan tidak dapat dijalankan: '.$hasil['galat'];
        }

        return 'PMeriode permintaan '.$hasil['period_start']->translatedFormat(Tanggal::TAMPIL)
            .' sampai '.$hasil['period_end']->translatedFormat(Tanggal::TAMPIL).'.';
    }

    /**
     * Saring baris menurut kata kunci pencarian tabel. Tabel berbasis array tidak punya query,
     * jadi Filament tidak bisa menyaringnya sendiri.
     */
    protected function saring(array $rows): array
    {
        $kata = trim((string) $this->getTableSearch());

        if ($kata === '') {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row): bool => str_contains(
            mb_strtolower((string) $row['name'].' '.(string) $row['code']),
            mb_strtolower($kata),
        )));
    }
}
