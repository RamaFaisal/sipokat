<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->defaultSort('order_date', 'desc')
            ->columns([
                TextColumn::make('order_code')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_date')
                    ->label('Tanggal')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Item')
                    ->alignCenter(),
                TextColumn::make('grand_total')
                    ->label('Total')
                    ->money('IDR')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
                // Tidak ada edit (S6): salah input → hapus → buat ulang.
                DeleteAction::make()
                    ->modalDescription('Stok yang terjual akan dikembalikan ke batch asalnya.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
