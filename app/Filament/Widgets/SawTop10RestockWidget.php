<?php

namespace App\Filament\Widgets;

use App\Models\SawCalculation;
use App\Models\SawCalculationResult;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SawTop10RestockWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $latest = SawCalculation::orderByDesc('calculated_at')->first();

        $description = $latest
            ? 'Hasil SAW terakhir: '.$latest->calculated_at->format('d M Y')
                .' (periode '.$latest->period_start->format('d M Y')
                .' - '.$latest->period_end->format('d M Y').')'
            : 'Belum ada perhitungan SAW. Jalankan via menu SPK Restock → Hitung Prioritas Restock.';

        $calculationId = $latest?->id ?? 0;

        return $table
            ->heading('Top 10 Prioritas Restock (SAW)')
            ->description($description)
            ->query(function () use ($calculationId): Builder {
                $query = SawCalculationResult::query()
                    ->where('saw_calculation_id', $calculationId)
                    ->with('medicine:id,code,name')
                    ->orderBy('sort_order'); // 10 baris teratas menurut urutan tampil, bukan "semua tingkat ≤ 10" (K9)

                $query->limit(10);

                return $query;
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('rank')
                    ->label('Tingkat')
                    ->tooltip('Nilai prioritas sama = tingkat sama')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 3 => 'danger',
                        $state <= 7 => 'warning',
                        default => 'gray',
                    })
                    ->width(1),
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->wrap(),
                TextColumn::make('c1_raw')
                    ->label('Stok / Min')
                    ->state(fn (SawCalculationResult $r) => $r->c1_stock === null ? number_format((float) $r->c1_raw, 2, ',', '.') : $r->c1_stock.' / '.$r->c1_min_stock)
                    ->alignEnd(),
                TextColumn::make('c2_raw')
                    ->label('Permintaan/Bln')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('c3_raw')
                    ->label('Sisa ED (hari)')
                    ->numeric()
                    ->placeholder('—')
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
