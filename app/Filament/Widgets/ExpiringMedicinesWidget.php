<?php

namespace App\Filament\Widgets;

use App\Models\MedicineStock;
use App\Support\AmbangEd;
use App\Support\BatchBersisa;
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
            ->recordClasses(fn (MedicineStock $record): ?string => AmbangEd::kelasBaris(BatchBersisa::sisaHari($record)))
            // Satu baris per batch, bukan per lapisan: batch yang dibeli dua kali tetap satu
            // tumpukan di rak (App\Support\BatchBersisa).
            ->query(fn (): Builder => BatchBersisa::query()->with('medicine:id,code,name')->orderBy('expired_date'))
            ->columns([
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->description(fn (MedicineStock $record): string => 'Batch '.($record->batch_number ?: 'tanpa nomor'))
                    ->wrap(),
                TextColumn::make('sisa')
                    ->label('Sisa')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('days_remaining')
                    ->label('Sisa Hari')
                    ->description(fn (MedicineStock $record): string => $record->expired_date->translatedFormat(Tanggal::BULAN_TAHUN))
                    ->state(fn (MedicineStock $record): int => BatchBersisa::sisaHari($record))
                    ->badge()
                    ->color(fn (int $state): string => AmbangEd::warna($state))
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir']);
    }
}
