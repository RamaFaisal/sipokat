<?php

namespace App\Filament\Widgets;

use App\Models\MedicineStock;
use App\Support\AmbangEd;
use App\Support\BatchBersisa;
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
            ->description('Obat dengan kedaluwarsa terdekat di atas. Warna mengikuti ambang '.AmbangEd::PANTAU.' hari.')
            ->recordClasses(fn (MedicineStock $record): ?string => AmbangEd::kelasBaris(BatchBersisa::sisaHari($record)))
            // Satu baris per obat, memakai ED terdekat di antara batch yang masih bersisa.
            // Kolomnya hanya nama dan sisa hari, jadi baris per batch akan tampil ganda tanpa
            // pembeda (App\Support\BatchBersisa::perObat).
            ->query(fn (): Builder => BatchBersisa::perObat()->with('medicine:id,code,name')->orderBy('expired_date'))
            ->columns([
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->wrap(),
                TextColumn::make('days_remaining')
                    ->label('Sisa Hari')
                    ->state(fn (MedicineStock $record): int => BatchBersisa::sisaHari($record))
                    ->badge()
                    ->color(fn (int $state): string => AmbangEd::warna($state))
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir']);
    }
}
