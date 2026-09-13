<?php

namespace App\Filament\Widgets;

use App\Models\MedicineStock;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringMedicinesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $today = now()->startOfDay();
        $threshold = $today->copy()->addDays(90);

        return $table
            ->heading('Obat Mendekati Kedaluwarsa')
            ->description('Batch yang masih bersisa dengan masa kedaluwarsa ≤ 90 hari.')
            ->query(function () use ($today, $threshold): Builder {
                // F5: lapisan (baris D kartu stok) yang sisanya > 0 — bukan item RO yang mungkin sudah habis terjual.
                $query = MedicineStock::layers()
                    ->withRemainingStock()
                    ->whereNotNull('expired_date')
                    ->whereBetween('expired_date', [$today->toDateString(), $threshold->toDateString()])
                    ->whereHas('medicine', fn (Builder $q) => $q->where('status', 'active'))
                    ->withSum('consumptions', 'qty')
                    ->with([
                        'medicine:id,code,name,stock_status',
                        'receiveOrder.supplier:id,name',
                    ])
                    ->orderBy('expired_date');

                $query->limit(5);

                return $query;
            })
            ->columns([
                TextColumn::make('medicine.code')
                    ->label('Kode'),
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->wrap(),
                TextColumn::make('batch_number')
                    ->label('Batch')
                    ->placeholder('—'),
                TextColumn::make('remaining')
                    ->label('Sisa Batch')
                    ->state(fn (MedicineStock $record): int => $record->remaining)
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('expired_date')
                    ->label('Tgl ED')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label('Sisa Hari')
                    ->state(fn (MedicineStock $record): int => (int) now()->startOfDay()->diffInDays($record->expired_date->startOfDay(), false))
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 30 => 'danger',
                        $state <= 60 => 'warning',
                        default => 'gray',
                    })
                    ->alignEnd(),
                TextColumn::make('receiveOrder.supplier.name')
                    ->label('Supplier'),
                TextColumn::make('medicine.stock_status')
                    ->label('Status Stok')
                    ->badge()
                    ->colors([
                        'danger' => 'empty',
                        'warning' => 'almost_empty',
                        'success' => 'available',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'empty' => 'Habis',
                        'almost_empty' => 'Menipis',
                        'available' => 'Tersedia',
                        default => $state,
                    }),
            ])
            ->paginated(false);
    }
}
