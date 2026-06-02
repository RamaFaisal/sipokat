<?php

namespace App\Filament\Widgets;

use App\Models\SawCalculation;
use App\Models\SawCalculationResult;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class SawTop10RestockWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $latest = SawCalculation::orderByDesc('calculated_at')->first();

        $description = $latest
            ? 'Snapshot SAW terakhir: ' . $latest->calculated_at->format('d M Y H:i')
                . ' (periode ' . $latest->period_start->format('d M Y')
                . ' - ' . $latest->period_end->format('d M Y') . ')'
            : 'Belum ada perhitungan SAW. Jalankan via menu SPK Restock → Hitung Prioritas Restock.';

        $calculationId = $latest?->id ?? 0;

        return $table
            ->heading('Top 10 Prioritas Restock (SAW)')
            ->description($description)
            ->query(fn (): Builder => SawCalculationResult::query()
                ->where('saw_calculation_id', $calculationId)
                ->with('medicine:id,code,name,dosage')
                ->orderBy('rank')
                ->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 3 => 'danger',
                        $state <= 7 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('medicine.code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->wrap(),
                TextColumn::make('c1_raw')
                    ->label('Stok')
                    ->numeric()
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
