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

    /** Baris yang ditampilkan; pencarian menyaring seluruh peringkat dulu, baru dipotong sebanyak ini. */
    private const BARIS = 5;

    public function table(Table $table): Table
    {
        try {
            $result = app(SawCalculationService::class)->calculateCached(today()->subDays(29), today());
            $rows = array_slice($this->saring($result['rows']), 0, self::BARIS);
            $description = 'Dihitung '.$result['calculated_at']->translatedFormat(Tanggal::TAMPIL_JAM)
            .' atas kondisi terkini (periode permintaan '.$result['period_start']->translatedFormat(Tanggal::TAMPIL)
            .' - '.$result['period_end']->translatedFormat(Tanggal::TAMPIL).')';
        } catch (\Throwable $e) {
            $rows = [];
            $description = 'Perhitungan tidak dapat dijalankan: '.$e->getMessage();
        }

        return $table
            ->heading('Prioritas Restock (SAW)')
            ->description($description)
            ->records(fn (): Collection => collect($rows))
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
