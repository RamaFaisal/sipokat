<?php

namespace App\Filament\Widgets;

use App\Models\Medicine;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stok seluruh obat aktif, yang paling sedikit di paling atas (K5).
 *
 * Pengurutan memakai kolom SQL `stok_tersedia` dari `Medicine::scopeWithAvailableStock()`, bukan
 * `StockCardService::availableStock()` per baris. Selain jauh lebih murah, ini juga menghapus
 * `orderByRaw("FIELD(...)")` yang khusus MySQL dan mengurutkan menurut tiga ember status, bukan
 * menurut sisa stok yang sebenarnya.
 */
class LowStockMedicinesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    /** Baris yang ditampilkan; pencarian menyaring seluruh obat dulu, baru dipotong sebanyak ini. */
    private const BARIS = 10;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stok Obat')
            ->description(self::BARIS.' obat dengan stok tersedia paling sedikit. Pakai pencarian untuk obat lain.')
            ->query(fn (): Builder => Medicine::query()
                ->withAvailableStock()
                ->where('status', 'active')
                ->orderBy('stok_tersedia')
                ->orderBy('name')
                ->limit(self::BARIS))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('stok_tersedia')
                    ->label('Sisa')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state, Medicine $record): string => match (true) {
                        (int) $state <= 0 => 'danger',
                        (int) $state < (int) $record->min_stock => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state, Medicine $record): string => max(0, (int) $state)
                        .' '.($record->unit?->name ?? ''))
                    ->alignEnd(),
            ])
            ->defaultSort('stok_tersedia')
            ->paginated(false)
            ->extraAttributes(['class' => 'sipokat-widget-gulir']);
    }
}
