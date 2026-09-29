<?php

namespace App\Filament\Widgets;

use App\Models\MedicineStock;
use App\Support\AmbangEd;
use App\Support\Tanggal;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringMedicinesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Obat Mendekati Kedaluwarsa')
            ->description('Seluruh batch bersisa, kedaluwarsa terdekat di atas. Warna mengikuti ambang '.AmbangEd::PANTAU.' hari.')
            ->recordClasses(fn (MedicineStock $record): ?string => AmbangEd::kelasBaris(self::sisaHari($record)))
            ->query(function (): Builder {
                // F5: lapisan (baris D kartu stok) yang sisanya > 0 bukan item RO yang mungkin sudah habis terjual.
                $query = MedicineStock::layers()
                    ->withRemainingStock()
                    ->whereNotNull('expired_date')
                    ->whereHas('medicine', fn (Builder $q) => $q->where('status', 'active'))
                    ->withSum('consumptions', 'qty')
                    ->with([
                        'medicine:id,code,name,stock_status',
                    ])
                    ->orderBy('expired_date');

                return $query;
            })
            ->columns([
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->description(fn (MedicineStock $record): string => 'Batch '.($record->batch_number ?: 'tanpa nomor'))
                    ->wrap(),
                TextColumn::make('remaining')
                    ->label('Sisa')
                    ->state(fn (MedicineStock $record): int => $record->remaining)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('days_remaining')
                    ->label('Sisa Hari')
                    ->description(fn (MedicineStock $record): string => $record->expired_date->translatedFormat(Tanggal::BULAN_TAHUN))
                    ->state(fn (MedicineStock $record): int => self::sisaHari($record))
                    ->badge()
                    ->color(fn (int $state): string => AmbangEd::warna($state))
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir']);
    }

    /** Sisa hari menuju ED lapisan ini; negatif berarti sudah kedaluwarsa. */
    private static function sisaHari(MedicineStock $record): int
    {
        return (int) now()->startOfDay()->diffInDays($record->expired_date->startOfDay(), false);
    }
}
