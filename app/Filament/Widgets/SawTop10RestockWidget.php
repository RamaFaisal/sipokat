<?php

namespace App\Filament\Widgets;

use App\Services\SawCalculationService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

/**
 * Top 10 prioritas restock. Sejak 2026-09-27 dihitung langsung saat dashboard dibuka
 * (tidak lagi membaca snapshot), sehingga selalu mencerminkan stok dan penjualan terkini.
 */
class SawTop10RestockWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        try {
            $result = app(SawCalculationService::class)->calculateCached(today()->subDays(29), today());
            // 10 baris teratas menurut urutan tampil, bukan "semua tingkat ≤ 10" (K9).
            $rows = array_slice($result['rows'], 0, 10);
            $description = 'Dihitung '.$result['calculated_at']->format('d M Y H:i')
            .' atas kondisi terkini (periode permintaan '.$result['period_start']->format('d M Y')
            .' - '.$result['period_end']->format('d M Y').')';
        } catch (\Throwable $e) {
            $rows = [];
            $description = 'Perhitungan tidak dapat dijalankan: '.$e->getMessage();
        }

        return $table
            ->heading('Top 10 Prioritas Restock (SAW)')
            ->description($description)
            ->records(fn (): Collection => collect($rows))
            ->paginated(false)
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
                TextColumn::make('c1_raw')
                    ->label('Stok / Min')
                    ->state(fn (array $record) => $record['c1_stock'] === null
                    ? number_format((float) $record['c1_raw'], 2, ',', '.')
                    : $record['c1_stock'].' / '.$record['c1_min_stock'])
                    ->alignEnd(),
                TextColumn::make('c2_raw')
                    ->label('Permintaan/Bln')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('c3_raw')
                    ->label('Sisa ED (hari)')
                    ->numeric()
                    ->placeholder('')
                    ->alignEnd(),
                TextColumn::make('preference_value')
                    ->label('Nilai Prioritas')
                    ->tooltip('Nilai preferensi SAW (V_i = Σ Wj × Rij). Semakin tinggi = semakin prioritas restock.')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->weight('bold'),
            ]);
    }
}
