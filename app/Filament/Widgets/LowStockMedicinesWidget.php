<?php

namespace App\Filament\Widgets;

use App\Models\Medicine;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockMedicinesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Obat Stok Menipis / Habis')
            ->description('Obat dengan status stok habis atau hampir habis (di bawah min stock).')
            ->query(function (): Builder {
                $query = Medicine::query()
                    ->whereIn('stock_status', ['empty', 'almost_empty'])
                    ->where('status', 'active')
                    ->orderByRaw("FIELD(stock_status, 'empty', 'almost_empty')")
                    ->orderBy('name');

                $query->limit(5);

                return $query;
            })
            ->columns([
                TextColumn::make('code')
                    ->label('Kode'),
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->wrap(),
                TextColumn::make('dosage')
                    ->label('Dosis'),
                TextColumn::make('min_stock')
                    ->label('Min Stok')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'danger' => 'empty',
                        'warning' => 'almost_empty',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'empty' => 'Habis',
                        'almost_empty' => 'Menipis',
                        default => $state,
                    }),
            ])
            ->paginated(false);
    }
}
