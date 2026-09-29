<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\LaporanKedaluwarsa;
use App\Filament\Pages\MedicineStockDetail;
use App\Models\MedicineStock;
use App\Support\AmbangEd;
use App\Support\BatchBersisa;
use App\Support\TautanWidget;
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

    /** Baris yang ditampilkan. Selebihnya lewat Laporan Akan Kedaluwarsa, dicapai dari judul widget. */
    private const BARIS = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading(TautanWidget::judul('Obat Mendekati Kedaluwarsa', LaporanKedaluwarsa::canAccess() ? LaporanKedaluwarsa::getUrl() : null))
            ->description(self::BARIS.' obat dengan kedaluwarsa terdekat.')
            ->recordClasses(fn (MedicineStock $record): ?string => AmbangEd::kelasBaris(BatchBersisa::sisaHari($record)))
            // Satu baris per obat, memakai ED terdekat di antara batch yang masih bersisa.
            // Kolomnya hanya nama dan sisa hari, jadi baris per batch akan tampil ganda tanpa
            // pembeda (App\Support\BatchBersisa::perObat).
            ->query(fn (): Builder => BatchBersisa::perObat()->with('medicine:id,code,name')->orderBy('expired_date')->limit(self::BARIS))
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
            ->recordUrl(fn (MedicineStock $record): ?string => MedicineStockDetail::canAccess()
                ? MedicineStockDetail::getUrl(['record' => $record->medicine_id])
                : null)
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir']);
    }
}
